(function () {
    'use strict';

    const V = window.CostSplitValidation;

    function fieldError(input) {
        const value = input.value;
        const max = input.maxLength > 0 ? input.maxLength : 0;
        switch (input.dataset.rule) {
            case 'required': return V.requiredError(value, max);
            case 'money': return V.amountError(value);
            case 'percent': return V.percentError(value);
            case 'percent-optional': return value.trim() === '' ? null : V.percentError(value);
            case 'email': return V.emailError(value);
            default: return null;
        }
    }

    function setError(form, name, message) {
        const el = form.querySelector('[data-error-for="' + CSS.escape(name) + '"]');
        if (el) {
            el.textContent = message || '';
        }
    }

    // Wiersze osób biorących udział w podziale; nowa osoba liczy się dopiero po wpisaniu imienia.
    function activeRows(form) {
        return [...form.querySelectorAll('[data-share-row]')].filter((row) =>
            !row.hasAttribute('data-share-new') || row.querySelector('[data-share-name]').value.trim() !== '');
    }

    // Przelicza udziały automatyczne i sumę. Zwraca false, gdy suma nie wynosi 100%.
    function validateShares(form) {
        const rows = activeRows(form);
        const explicit = [];
        let invalid = false;
        for (const row of rows) {
            const raw = row.querySelector('[data-share]').value.trim();
            const bp = raw === '' ? null : V.parsePercent(raw);
            invalid = invalid || (raw !== '' && bp === null);
            explicit.push(bp);
        }

        for (const cell of form.querySelectorAll('[data-share-effective]')) {
            cell.textContent = '';
        }
        const sumEl = form.querySelector('[data-shares-sum]');
        if (invalid) {
            sumEl.textContent = '–';
            setError(form, 'shares', null);
            return true; // błędy pojedynczych pól zgłaszane są osobno
        }

        const shares = V.effectiveShares(explicit);
        rows.forEach((row, i) => {
            row.querySelector('[data-share-effective]').textContent = V.formatPercent(shares[i]);
        });
        sumEl.textContent = V.formatPercent(shares.reduce((a, b) => a + b, 0));
        const error = shares.length ? V.sharesSumError(shares) : null;
        setError(form, 'shares', error);
        return error === null;
    }

    function validateNewPerson(form) {
        const row = form.querySelector('[data-share-new]');
        const name = row.querySelector('[data-share-name]');
        const otherFilled = [...row.querySelectorAll('input')].some((i) => i !== name && i.value.trim() !== '');
        const error = name.value.trim() === '' && otherFilled ? 'Podaj imię nowej osoby.' : null;
        setError(form, name.name, error);
        return error === null;
    }

    function validateForm(form) {
        let valid = true;
        for (const input of form.querySelectorAll('[data-rule]')) {
            const error = fieldError(input);
            setError(form, input.name, error);
            valid = valid && error === null;
        }
        if (form.hasAttribute('data-shares-form')) {
            valid = validateNewPerson(form) && valid;
            valid = validateShares(form) && valid;
        }
        if (form.hasAttribute('data-repayment-form')) {
            const from = form.elements.from;
            const to = form.elements.to;
            if (from && to && from.value !== '' && from.value === to.value) {
                setError(form, 'from', 'Wybierz dwie różne osoby.');
                valid = false;
            }
        }
        return valid;
    }

    for (const form of document.querySelectorAll('form[data-validate]')) {
        form.addEventListener('submit', (event) => {
            if (!validateForm(form)) {
                event.preventDefault();
                const first = [...form.querySelectorAll('.error')].find((el) => el.textContent !== '');
                if (first) {
                    first.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            }
        });
        form.addEventListener('change', (event) => {
            if (event.target.dataset.rule) {
                setError(form, event.target.name, fieldError(event.target));
            }
        });
        if (form.hasAttribute('data-shares-form')) {
            form.addEventListener('input', () => validateShares(form));
            validateShares(form);
        }
    }

    // Reguła 100% przy usuwaniu osoby: pozostałe zapisane udziały muszą sumować się do 100%.
    function deletePersonError(personId) {
        const shares = [...document.querySelectorAll('[data-person-id]')]
            .filter((row) => row.dataset.personId !== personId)
            .map((row) => (row.dataset.savedShare === '' ? null : parseInt(row.dataset.savedShare, 10)));
        const error = shares.length ? V.sharesSumError(V.effectiveShares(shares)) : null;
        return error ? 'Nie można usunąć osoby. ' + error + ' Najpierw zmień udziały.' : null;
    }

    for (const form of document.querySelectorAll('form[data-confirm]')) {
        form.addEventListener('submit', (event) => {
            const error = form.dataset.blocked || (form.dataset.deletePerson ? deletePersonError(form.dataset.deletePerson) : null);
            const field = form.dataset.blockedFor || 'shares';
            if (error) {
                event.preventDefault();
                setError(document, field, error);
                document.querySelector('[data-error-for="' + field + '"]')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            } else if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    }

    for (const button of document.querySelectorAll('[data-copy]')) {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                button.textContent = 'Skopiowano';
            } catch {
                button.previousElementSibling.select();
            }
        });
    }
})();
