// Run with: node --test tests/js
const test = require('node:test');
const assert = require('node:assert/strict');
const RankIcons = require('../../webapp/assets/js/lib/rank-icons.js');

test('medal: returns svg markup with the correct tier class for every valid tier', () => {
  for (const tier of RankIcons.VALID_TIERS) {
    const svg = RankIcons.medal(tier);
    assert.match(svg, /<svg/);
    assert.match(svg, new RegExp(`rank-icon--${tier}\\b`));
  }
});

test('medal: unknown tier returns an empty string rather than throwing', () => {
  assert.equal(RankIcons.medal('platinum-plus'), '');
  assert.equal(RankIcons.medal(undefined), '');
});

test('medal: diamond tier embeds a unique gradient id each call, not reused', () => {
  const first = RankIcons.medal('diamond');
  const second = RankIcons.medal('diamond');
  const firstId = first.match(/id="(rankDiamondGrad\d+)"/)[1];
  const secondId = second.match(/id="(rankDiamondGrad\d+)"/)[1];
  assert.notEqual(firstId, secondId);
});

test('medalByPlace: first three places map to gold/silver/bronze', () => {
  assert.match(RankIcons.medalByPlace(0), /rank-icon--gold/);
  assert.match(RankIcons.medalByPlace(1), /rank-icon--silver/);
  assert.match(RankIcons.medalByPlace(2), /rank-icon--bronze/);
});

test('medalByPlace: fourth place and beyond returns empty string (caller falls back to plain text)', () => {
  assert.equal(RankIcons.medalByPlace(3), '');
  assert.equal(RankIcons.medalByPlace(100), '');
});

test('trophy: returns non-empty svg markup', () => {
  const svg = RankIcons.trophy();
  assert.match(svg, /<svg/);
  assert.match(svg, /trophy-icon/);
});
