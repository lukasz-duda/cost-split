const test = require('node:test');
const assert = require('node:assert');
const V = require('../app/assets/validation.js');

test('parseMoney', () => {
    assert.strictEqual(V.parseMoney('12,5'), 1250);
    assert.strictEqual(V.parseMoney('12.05'), 1205);
    assert.strictEqual(V.parseMoney('1 234,56'), 123456);
    assert.strictEqual(V.parseMoney('1,234'), null);
    assert.strictEqual(V.parseMoney('-5'), null);
});

test('amountError', () => {
    assert.strictEqual(V.amountError('0'), 'Kwota musi być większa od zera.');
    assert.strictEqual(V.amountError('0,01'), null);
    assert.strictEqual(V.amountError('10000000,01'), 'Kwota jest zbyt duża.');
});

test('parsePercent', () => {
    assert.strictEqual(V.parsePercent('33,33'), 3333);
    assert.strictEqual(V.parsePercent('50%'), 5000);
    assert.strictEqual(V.parsePercent('100,01'), null);
});

test('sharesSumError: suma udziałów jest równa 100%', () => {
    assert.strictEqual(V.sharesSumError([3333, 3333, 3334]), null);
    assert.strictEqual(V.sharesSumError([3333, 3333, 3333]), 'Suma udziałów musi wynosić 100% (obecnie 99,99%).');
    assert.strictEqual(V.sharesSumError([5000, 6000]), 'Suma udziałów musi wynosić 100% (obecnie 110%).');
});

test('emailError', () => {
    assert.strictEqual(V.emailError(''), null);
    assert.strictEqual(V.emailError('a@b.pl'), null);
    assert.strictEqual(V.emailError('ab.pl'), 'Nieprawidłowy adres e-mail.');
});

test('effectiveShares: reszta do 100% dzielona po równo', () => {
    assert.deepStrictEqual(V.effectiveShares([null, null]), [5000, 5000]);
    assert.deepStrictEqual(V.effectiveShares([null, null, null]), [3334, 3333, 3333]);
    assert.deepStrictEqual(V.effectiveShares([2000, null, null]), [2000, 4000, 4000]);
    assert.deepStrictEqual(V.effectiveShares([3000, 3000]), [3000, 3000]);
    assert.deepStrictEqual(V.effectiveShares([8000, 4000, null]), [8000, 4000, 0]);
    assert.strictEqual(V.sharesSumError(V.effectiveShares([3000, 3000])), 'Suma udziałów musi wynosić 100% (obecnie 60%).');
});
