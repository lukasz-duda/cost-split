// Reguły walidacji po stronie klienta. Odpowiednik po stronie serwera: src/Validation.php, src/Shares.php.
(function (root) {
    'use strict';

    const FULL_SHARE_BP = 10000;
    const MAX_AMOUNT = 1000000000;

    function digits(fraction) {
        return parseInt((fraction || '').padEnd(2, '0'), 10);
    }

    function parseMoney(value) {
        const m = String(value).trim().replace(/[ \u00A0]/g, '').match(/^(\d{1,10})(?:[.,](\d{1,2}))?$/);
        return m ? parseInt(m[1], 10) * 100 + digits(m[2]) : null;
    }

    function parsePercent(value) {
        const m = String(value).trim().replace(/[ %]/g, '').match(/^(\d{1,3})(?:[.,](\d{1,2}))?$/);
        if (!m) {
            return null;
        }
        const bp = parseInt(m[1], 10) * 100 + digits(m[2]);
        return bp <= FULL_SHARE_BP ? bp : null;
    }

    function formatPercent(bp) {
        return (bp / 100).toFixed(2).replace(/\.?0+$/, '').replace('.', ',') + '%';
    }

    function amountError(value) {
        const amount = parseMoney(value);
        if (amount === null) {
            return 'Podaj kwotę w PLN, np. 12,50.';
        }
        if (amount <= 0) {
            return 'Kwota musi być większa od zera.';
        }
        if (amount > MAX_AMOUNT) {
            return 'Kwota jest zbyt duża.';
        }
        return null;
    }

    function percentError(value) {
        return parsePercent(value) === null
            ? 'Podaj udział od 0 do 100%, maksymalnie 2 miejsca po przecinku.'
            : null;
    }

    function requiredError(value, maxLength) {
        const v = String(value).trim();
        if (v === '') {
            return 'Pole jest wymagane.';
        }
        if (maxLength && [...v].length > maxLength) {
            return 'Maksymalnie ' + maxLength + ' znaków.';
        }
        return null;
    }

    function emailError(value) {
        const v = String(value).trim();
        return v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Nieprawidłowy adres e-mail.';
    }

    // Osoba z udziałem null dostaje równą część tego, co zostało do 100%. Odpowiednik: src/Shares.php.
    function effectiveShares(explicitBp) {
        const auto = explicitBp.filter((bp) => bp === null).length;
        const rest = Math.max(0, FULL_SHARE_BP - explicitBp.reduce((a, bp) => a + (bp === null ? 0 : bp), 0));
        const base = auto > 0 ? Math.floor(rest / auto) : 0;
        let extra = auto > 0 ? rest - base * auto : 0;
        return explicitBp.map((bp) => (bp === null ? base + (extra-- > 0 ? 1 : 0) : bp));
    }

    // Reguła biznesowa: suma udziałów jest równa 100%.
    function sharesSumError(sharesBp) {
        const sum = sharesBp.reduce((a, b) => a + b, 0);
        return sum === FULL_SHARE_BP ? null : 'Suma udziałów musi wynosić 100% (obecnie ' + formatPercent(sum) + ').';
    }

    const api = { parseMoney, parsePercent, formatPercent, amountError, percentError, requiredError, emailError, effectiveShares, sharesSumError };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.CostSplitValidation = api;
    }
})(this);
