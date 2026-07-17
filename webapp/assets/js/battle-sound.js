// Short generated tones for Quiz Battle (no audio files/licensing to worry about).
// AudioContext is created lazily on the first user gesture to satisfy browser
// autoplay policy; mute state persists across reloads via localStorage.
const BattleSound = (() => {
  const MUTE_KEY = 'battleSoundMuted';
  let ctx = null;

  function isMuted() {
    return localStorage.getItem(MUTE_KEY) === '1';
  }

  function setMuted(muted) {
    localStorage.setItem(MUTE_KEY, muted ? '1' : '0');
  }

  function ensureContext() {
    if (!ctx) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      ctx = new AudioCtx();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function tone(freq, duration, type, delay, gainValue) {
    if (isMuted()) return;
    const audioCtx = ensureContext();
    if (!audioCtx) return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = type || 'sine';
    osc.frequency.value = freq;
    const startTime = audioCtx.currentTime + (delay || 0);
    gain.gain.setValueAtTime(gainValue || 0.15, startTime);
    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.start(startTime);
    osc.stop(startTime + duration);
  }

  // A short burst of filtered white noise with a rising bandpass sweep and a
  // fade-in/out envelope — no audio file needed, but reads as a crowd/applause
  // swell rather than a synth tone.
  function noiseSwell(duration, delay, freqStart, freqEnd, gainPeak) {
    if (isMuted()) return;
    const audioCtx = ensureContext();
    if (!audioCtx) return;
    const bufferSize = Math.floor(audioCtx.sampleRate * duration);
    const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;

    const noise = audioCtx.createBufferSource();
    noise.buffer = buffer;

    const filter = audioCtx.createBiquadFilter();
    filter.type = 'bandpass';
    filter.Q.value = 0.6;
    const startTime = audioCtx.currentTime + (delay || 0);
    filter.frequency.setValueAtTime(freqStart, startTime);
    filter.frequency.linearRampToValueAtTime(freqEnd, startTime + duration);

    const gain = audioCtx.createGain();
    gain.gain.setValueAtTime(0, startTime);
    gain.gain.linearRampToValueAtTime(gainPeak, startTime + duration * 0.35);
    gain.gain.linearRampToValueAtTime(0, startTime + duration);

    noise.connect(filter);
    filter.connect(gain);
    gain.connect(audioCtx.destination);
    noise.start(startTime);
    noise.stop(startTime + duration);
  }

  return {
    init() { ensureContext(); },
    getContext: ensureContext,
    isMuted,
    setMuted,
    correct() { tone(660, 0.12, 'sine', 0, 0.15); tone(880, 0.15, 'sine', 0.1, 0.15); },
    wrong() { tone(180, 0.3, 'sawtooth', 0, 0.12); },
    tick() { tone(1000, 0.05, 'square', 0, 0.08); },
    go() { tone(880, 0.09, 'square', 0, 0.18); tone(1320, 0.2, 'sine', 0.08, 0.2); },
    victory() {
      [523, 659, 784, 1046].forEach((freq, i) => tone(freq, 0.25, 'sine', i * 0.15, 0.15));
    },
    cheer() {
      // Layered crowd swell + scattered "clap" transients + a bright ascending
      // chime on top — a single noiseSwell alone reads as background hiss, not
      // an actual cheer, so this stacks several elements at a louder gain.
      noiseSwell(2.2, 0, 500, 3200, 0.4);
      for (let i = 0; i < 8; i++) {
        noiseSwell(0.12, 0.05 + Math.random() * 1.8, 1800, 4500, 0.32);
      }
      [523, 659, 784, 1046, 1318].forEach((freq, i) => tone(freq, 0.3, 'sine', 0.3 + i * 0.09, 0.18));
    },
  };
})();

// Soft looping ambient background music (Am-F-C-G pad chords), generated the same
// way as the sound effects — no audio file to host or license. Shares BattleSound's
// AudioContext and mute state so the one mute button controls both.
const BattleMusic = (() => {
  const CHORDS = [
    [220.00, 261.63, 329.63], // Am
    [174.61, 220.00, 261.63], // F
    [261.63, 329.63, 392.00], // C
    [196.00, 246.94, 293.66], // G
  ];
  const CHORD_SECONDS = 4;
  const NOTE_GAIN = 0.035;

  let chordIndex = 0;
  let playing = false;
  let timeoutHandle = null;

  function playChord(freqs) {
    if (BattleSound.isMuted()) return;
    const ctx = BattleSound.getContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    freqs.forEach((freq) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'triangle';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0, now);
      gain.gain.linearRampToValueAtTime(NOTE_GAIN, now + 0.4);
      gain.gain.linearRampToValueAtTime(0, now + CHORD_SECONDS - 0.3);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now);
      osc.stop(now + CHORD_SECONDS);
    });
  }

  function loop() {
    if (!playing) return;
    playChord(CHORDS[chordIndex]);
    chordIndex = (chordIndex + 1) % CHORDS.length;
    timeoutHandle = setTimeout(loop, CHORD_SECONDS * 1000);
  }

  return {
    start() {
      if (playing) return;
      playing = true;
      chordIndex = 0;
      loop();
    },
    stop() {
      playing = false;
      if (timeoutHandle) clearTimeout(timeoutHandle);
      timeoutHandle = null;
    },
    isPlaying() { return playing; },
  };
})();
