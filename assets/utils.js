// assets/utils.js — Shared helpers loaded on every authenticated page (via _sidebar.php).

// Escapes a value before interpolating it into innerHTML / template strings.
// Use it for every user-controlled string (product, material and client names,
// references, table labels, server error messages...).
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
window.escapeHtml = escapeHtml;

// Only allows #rgb / #rrggbb colors inside style attributes.
function safeColor(value, fallback = '#fce8f0') {
    return /^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(value || '') ? value : fallback;
}
window.safeColor = safeColor;
