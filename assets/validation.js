// assets/validation.js — Shared field rules and input filters (loaded on every
// authenticated page via _sidebar.php). Mirrors lib/validation.php so the browser
// and the API accept exactly the same values.
//
// Declarative usage: <input data-rule="name|phone|cedula|email|username">
// then FieldRules.init(root) (runs automatically on DOMContentLoaded).
// Number inputs (<input type="number">) are guarded globally: no "e", "+",
// no "-" when min >= 0, and no decimals when step is a whole number.

const FieldRules = (() => {
  const CEDULA_RE = /^([VEJGP]?)(\d{6,9})$/;
  const PHONE_RE = /^0(?:412|414|416|422|424|426|2\d{2})\d{7}$/;
  const NAME_RE = /^\p{L}[\p{L}\p{M}' -]*$/u;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const HINT_TIMEOUT_MS = 2600;

  // "v-12.345.678" -> "V12345678"
  function compactCedula(value) {
    return String(value ?? '').toUpperCase().replace(/[\s.\-]+/g, '').trim();
  }

  // Canonical stored form ("12345678" for V, "E12345678" for other prefixes) or null.
  function normalizeCedula(value) {
    const match = CEDULA_RE.exec(compactCedula(value));
    if (!match) return null;
    return match[1] === '' || match[1] === 'V' ? match[2] : match[1] + match[2];
  }

  // Display form: "V-12345678" / "E-12345678".
  function formatCedula(stored) {
    const compact = compactCedula(stored);
    if (!compact) return '';
    const match = /^([VEJGP])(\d+)$/.exec(compact);
    return match ? `${match[1]}-${match[2]}` : `V-${compact}`;
  }

  // True when a free-text search term should be treated as a cedula.
  function looksLikeCedula(term) {
    return /^[VEJGP]?\d+$/.test(compactCedula(term));
  }

  // Lowercase + strip accents, for accent-insensitive comparisons.
  function foldText(value) {
    return String(value ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  }

  function isValidCedula(value) {
    return normalizeCedula(value) !== null;
  }

  function isValidName(value) {
    const clean = String(value ?? '').trim();
    return clean.length >= 2 && clean.length <= 100 && NAME_RE.test(clean);
  }

  function isValidPhone(value) {
    return PHONE_RE.test(String(value ?? '').replace(/\D/g, ''));
  }

  function isValidEmail(value) {
    return EMAIL_RE.test(String(value ?? '').trim());
  }

  const RULES = {
    name: {
      maxLength: 100,
      inputMode: 'text',
      autocomplete: 'name',
      filter: v => v.replace(/[^\p{L}\p{M}' -]/gu, '').replace(/ {2,}/g, ' ').replace(/^[ '-]+/, ''),
      filteredMessage: 'Solo se permiten letras, espacios, apóstrofo y guion.',
      validate: v => isValidName(v) ? '' : 'Ingresa un nombre válido (solo letras, mínimo 2).',
    },
    phone: {
      maxLength: 11,
      inputMode: 'numeric',
      autocomplete: 'tel',
      filter: v => v.replace(/\D/g, '').slice(0, 11),
      filteredMessage: 'Solo se permiten números (máximo 11 dígitos).',
      validate: v => isValidPhone(v) ? '' : 'Teléfono inválido: 11 dígitos que inicien en 0412, 0414, 0416, 0422, 0424, 0426 o 02xx.',
    },
    cedula: {
      maxLength: 14,
      inputMode: 'text',
      autocomplete: 'off',
      filter: v => v.toUpperCase().replace(/[^VEJGP0-9.\- ]/g, '').replace(/(?!^)[VEJGP]/g, ''),
      filteredMessage: 'Usa solo números, con prefijo opcional V, E, J, G o P.',
      validate: v => isValidCedula(v) ? '' : 'Cédula inválida: 6 a 9 dígitos, con prefijo opcional V, E, J, G o P.',
    },
    email: {
      maxLength: 120,
      inputMode: 'email',
      autocomplete: 'email',
      filter: v => v.replace(/\s/g, ''),
      filteredMessage: 'El correo no puede contener espacios.',
      validate: v => isValidEmail(v) ? '' : 'Correo electrónico inválido.',
    },
    username: {
      maxLength: 50,
      inputMode: 'text',
      autocomplete: 'off',
      filter: v => v.replace(/[^A-Za-z0-9._-]/g, ''),
      filteredMessage: 'Solo letras sin acentos, números, punto, guion o guion bajo.',
      validate: v => /^[A-Za-z0-9._-]{3,50}$/.test(v) ? '' : 'El usuario debe tener 3 a 50 caracteres válidos.',
    },
  };

  let hintCounter = 0;

  function hintFor(input) {
    if (input._fieldHint) return input._fieldHint;
    const hint = document.createElement('p');
    hint.id = input.id ? `${input.id}-hint` : `field-hint-${++hintCounter}`;
    hint.className = 'field-hint hidden';
    hint.setAttribute('aria-live', 'polite');
    const anchor = input.parentElement && input.parentElement.classList.contains('relative') ? input.parentElement : input;
    anchor.insertAdjacentElement('afterend', hint);
    const describedBy = (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
    if (!describedBy.includes(hint.id)) describedBy.push(hint.id);
    input.setAttribute('aria-describedby', describedBy.join(' '));
    input._fieldHint = hint;
    return hint;
  }

  function showError(input, message) {
    const hint = hintFor(input);
    clearTimeout(input._fieldHintTimer);
    hint.textContent = message;
    hint.classList.remove('hidden', 'field-hint-warn');
    hint.classList.add('field-hint-error');
    input.classList.add('field-invalid');
    input.setAttribute('aria-invalid', 'true');
  }

  function showNotice(input, message) {
    const hint = hintFor(input);
    if (input.classList.contains('field-invalid')) return;
    hint.textContent = message;
    hint.classList.remove('hidden', 'field-hint-error');
    hint.classList.add('field-hint-warn');
    clearTimeout(input._fieldHintTimer);
    input._fieldHintTimer = setTimeout(() => hint.classList.add('hidden'), HINT_TIMEOUT_MS);
  }

  function clearError(input) {
    input.classList.remove('field-invalid');
    input.removeAttribute('aria-invalid');
    if (input._fieldHint) input._fieldHint.classList.add('hidden');
  }

  // Returns '' when valid, otherwise a Spanish message.
  function check(input) {
    const rule = RULES[input.dataset.rule];
    const value = input.value.trim();
    if (value === '') return input.required ? 'Este campo es obligatorio.' : '';
    return rule ? rule.validate(value) : '';
  }

  function applyFilter(input) {
    const rule = RULES[input.dataset.rule];
    if (!rule) return;
    const before = input.value;
    const after = rule.filter(before);
    if (after === before) return;
    const caret = input.selectionStart;
    input.value = after;
    if (typeof caret === 'number') {
      const pos = Math.max(0, caret - (before.length - after.length));
      try { input.setSelectionRange(pos, pos); } catch (_) { /* not supported for some types */ }
    }
    showNotice(input, rule.filteredMessage);
  }

  function attach(input) {
    const rule = RULES[input.dataset.rule];
    if (!rule || input._fieldRulesAttached) return;
    input._fieldRulesAttached = true;
    if (!input.hasAttribute('maxlength')) input.maxLength = rule.maxLength;
    if (!input.hasAttribute('inputmode')) input.setAttribute('inputmode', rule.inputMode);
    if (!input.hasAttribute('autocomplete')) input.setAttribute('autocomplete', rule.autocomplete);

    input.addEventListener('input', () => {
      applyFilter(input);
      if (input.classList.contains('field-invalid') && check(input) === '') clearError(input);
    });
    input.addEventListener('blur', () => {
      const message = check(input);
      if (message && input.value.trim() !== '') showError(input, message);
      else if (!message) clearError(input);
    });
  }

  function init(root = document) {
    root.querySelectorAll('input[data-rule]').forEach(attach);
  }

  // Validates every [data-rule] field in the form; focuses the first invalid one.
  function validateForm(form) {
    let firstInvalid = null;
    form.querySelectorAll('input[data-rule]').forEach(input => {
      const message = check(input);
      if (message) {
        showError(input, message);
        if (!firstInvalid) firstInvalid = input;
      } else {
        clearError(input);
      }
    });
    if (firstInvalid) firstInvalid.focus();
    return firstInvalid === null;
  }

  function reset(form) {
    form.querySelectorAll('input[data-rule]').forEach(clearError);
  }

  // --- Global guard for <input type="number"> -------------------------------
  function isNonNegative(input) {
    return input.min !== '' && Number(input.min) >= 0;
  }

  function isIntegerOnly(input) {
    if (input.dataset.integer === 'true') return true;
    const step = input.getAttribute('step');
    return step !== null && step !== 'any' && Number.isInteger(Number(step)) && Number(step) >= 1;
  }

  function isNumberInput(target) {
    return target instanceof HTMLInputElement && target.type === 'number';
  }

  document.addEventListener('keydown', event => {
    const input = event.target;
    if (!isNumberInput(input) || event.ctrlKey || event.metaKey) return;
    if (['e', 'E', '+'].includes(event.key)
        || (event.key === '-' && isNonNegative(input))
        || ((event.key === '.' || event.key === ',') && isIntegerOnly(input))) {
      event.preventDefault();
    }
  }, true);

  document.addEventListener('paste', event => {
    const input = event.target;
    if (!isNumberInput(input)) return;
    const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
    const pattern = isIntegerOnly(input)
      ? (isNonNegative(input) ? /^\d+$/ : /^-?\d+$/)
      : (isNonNegative(input) ? /^\d*[.,]?\d+$/ : /^-?\d*[.,]?\d+$/);
    if (!pattern.test(text.trim())) event.preventDefault();
  }, true);

  document.addEventListener('focusin', event => {
    const input = event.target;
    if (isNumberInput(input) && !input.hasAttribute('inputmode')) {
      input.setAttribute('inputmode', isIntegerOnly(input) ? 'numeric' : 'decimal');
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }

  return {
    RULES,
    compactCedula, normalizeCedula, formatCedula, looksLikeCedula, foldText,
    isValidCedula, isValidName, isValidPhone, isValidEmail,
    attach, init, check, showError, clearError, validateForm, reset,
  };
})();

window.FieldRules = FieldRules;
