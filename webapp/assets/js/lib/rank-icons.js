// Inline-SVG medal/trophy icons, replacing emoji across the leaderboard and
// battle results screens. Colors come from CSS (`currentColor` + a
// `.rank-icon--<tier>` class) so the same shape is reused for every tier
// instead of shipping five separate image files -- matching this project's
// no-build, hand-authored-asset convention (same reasoning as the coin
// sprite and the neon-gradient CSS already in style.css).
//
// Loaded as a classic <script> tag (attaches to window) and via Node
// require() under the test runner, same UMD pattern as topic-progress.js.
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = factory();
  } else {
    root.RankIcons = factory();
  }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  var VALID_TIERS = ['bronze', 'silver', 'gold', 'platinum', 'diamond'];
  var PLACE_TIERS = ['gold', 'silver', 'bronze']; // 1st/2nd/3rd table position

  var gradientCounter = 0;

  // A medal disc with two ribbon tails. Diamond gets a prismatic gradient
  // fill (a bit more flourish for the top tier) instead of a flat color --
  // needs a unique gradient id per call since the markup is inserted via
  // innerHTML, potentially many times on one page (a full leaderboard table).
  function medal(tier) {
    if (VALID_TIERS.indexOf(tier) === -1) { return ''; }

    var fill = 'currentColor';
    var defs = '';
    if (tier === 'diamond') {
      var gradId = 'rankDiamondGrad' + (gradientCounter++);
      fill = 'url(#' + gradId + ')';
      defs = '<defs><linearGradient id="' + gradId + '" x1="0" y1="0" x2="1" y2="1">'
        + '<stop offset="0%" stop-color="#00f3ff"/>'
        + '<stop offset="100%" stop-color="#ff007f"/>'
        + '</linearGradient></defs>';
    }

    return '<svg class="rank-icon rank-icon--' + tier + '" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
      + defs
      + '<path d="M7 2 L9.5 9 L5 9 Z" fill="' + fill + '" opacity="0.85"/>'
      + '<path d="M17 2 L19 9 L14.5 9 Z" fill="' + fill + '" opacity="0.85"/>'
      + '<circle cx="12" cy="15" r="7" fill="' + fill + '"/>'
      + '<circle cx="12" cy="15" r="4.3" fill="none" stroke="rgba(0,0,0,0.28)" stroke-width="1"/>'
      + '</svg>';
  }

  // Table-position medal for rows 1/2/3; anything past 3rd place returns ''
  // so callers fall back to their own plain "4.", "5." etc. text, same as
  // the emoji array's `medals[i] || i + 1` pattern it replaces.
  function medalByPlace(index) {
    var tier = PLACE_TIERS[index];
    return tier ? medal(tier) : '';
  }

  function trophy() {
    return '<svg class="trophy-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">'
      + '<path d="M6 3h12v2a6 6 0 0 1-3 5.2V13a3 3 0 0 1-6 0v-2.8A6 6 0 0 1 6 5V3z" fill="currentColor"/>'
      + '<path d="M4 4h2v3a4 4 0 0 1-3-3.9V4z" fill="currentColor"/>'
      + '<path d="M20 4h-2v3a4 4 0 0 1 3-3.9V4z" fill="currentColor"/>'
      + '<rect x="10" y="16" width="4" height="3" fill="currentColor"/>'
      + '<rect x="7" y="19" width="10" height="2" rx="1" fill="currentColor"/>'
      + '</svg>';
  }

  return { medal: medal, medalByPlace: medalByPlace, trophy: trophy, VALID_TIERS: VALID_TIERS };
});
