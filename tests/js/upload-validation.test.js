// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const UploadValidation = require('../../webapp/assets/js/lib/upload-validation.js');

const ALLOWED = ['png', 'jpg', 'jpeg'];
const MAX_BYTES = 2 * 1024 * 1024;

test('validateFileClientSide: a valid small png passes (returns null)', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'screenshot.png', size: 500_000 }, ALLOWED, MAX_BYTES);
  assert.equal(result, null);
});

test('validateFileClientSide: a huge installer is rejected instantly on size, before any upload', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'OllamaSetup.exe', size: 300 * 1024 * 1024 }, ALLOWED, MAX_BYTES);
  assert.match(result, /File type not allowed/);
});

test('validateFileClientSide: a huge but correctly-typed image is rejected on size', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'huge.png', size: 50 * 1024 * 1024 }, ALLOWED, MAX_BYTES);
  assert.match(result, /too large/);
  assert.match(result, /50\.0MB/);
});

test('validateFileClientSide: extension check is case-insensitive', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'screenshot.PNG', size: 1000 }, ALLOWED, MAX_BYTES);
  assert.equal(result, null);
});

test('validateFileClientSide: a file exactly at the size cap passes', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'exact.png', size: MAX_BYTES }, ALLOWED, MAX_BYTES);
  assert.equal(result, null);
});

test('validateFileClientSide: missing extension is rejected', () => {
  const result = UploadValidation.validateFileClientSide({ name: 'noextension', size: 1000 }, ALLOWED, MAX_BYTES);
  assert.match(result, /File type not allowed/);
});

test('validateFileClientSide: a missing/malformed file object fails closed, not throws', () => {
  assert.doesNotThrow(() => UploadValidation.validateFileClientSide(null, ALLOWED, MAX_BYTES));
  assert.match(UploadValidation.validateFileClientSide(null, ALLOWED, MAX_BYTES), /File type not allowed/);
});
