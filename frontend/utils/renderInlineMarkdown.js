export function renderInlineMarkdown(markdown, helpers) {
  const sanitizeHTML = helpers?.sanitizeHTML;
  const escapeText = helpers?.escapeText;
  const escapeAttr = helpers?.escapeAttr;
  const inline = helpers?.inline || false; // NEW: inline mode flag

  if (markdown === null || markdown === undefined) {
    return '';
  }

  let source = typeof markdown === 'string' ? markdown : String(markdown);
  const trimmed = source.trim();
  if (trimmed === '') {
    return '';
  }

  const sanitize = typeof sanitizeHTML === 'function'
    ? sanitizeHTML
    : ((html) => html);

  const escapeLabel = typeof escapeText === 'function'
    ? (value) => escapeText(value)
    : (value) => String(value);

  const escapeAttribute = typeof escapeAttr === 'function'
    ? (value) => escapeAttr(value)
    : (value) => String(value);

  // If markup contains block-level/unsupported tags, trust legacy HTML and bail out early
  const disallowedTagPattern = /<(?!\/?(?:a|b|strong|i|em|u|s|strike|span|code|br)\b)[a-z][^>]*>/i;
  if (disallowedTagPattern.test(trimmed)) {
    return sanitize(source);
  }

  source = source.replace(/\r\n?/g, '\n');
  // Unescape basic escaped markdown characters (\* \_ etc.)
  source = source.replace(/\\([*_~`\[\]])/g, '$1');

  // Links [text](url)
  source = source.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/g, (_, label, href, title) => {
    const safeHref = escapeAttribute((href || '').trim());
    const titleAttr = title ? ` title="${escapeAttribute(title)}"` : '';
    return `<a href="${safeHref}"${titleAttr}>${escapeLabel(label)}</a>`;
  });

  // Bold **text** or __text__
  source = source.replace(/\*\*([\s\S]+?)\*\*/g, '<strong>$1</strong>');
  source = source.replace(/__([\s\S]+?)__/g, '<strong>$1</strong>');

  // Strikethrough ~~text~~
  source = source.replace(/~~([\s\S]+?)~~/g, '<s>$1</s>');

  // Italic *text* or _text_
  source = source.replace(/(^|[^*])\*([^*\n]+?)\*(?!\*)/g, (_, prefix, value) => `${prefix}<em>${value}</em>`);
  source = source.replace(/(^|[^_])_([^_\n]+?)_(?!_)/g, (_, prefix, value) => `${prefix}<em>${value}</em>`);

  // NEW: If inline mode, do NOT wrap in <p> tags
  if (inline) {
    return sanitize(source.replace(/\n/g, '<br>'));
  }

  // Block mode: wrap in <p> tags
  const paragraphs = source
    .split(/\n{2,}/)
    .map((block) => block.trim())
    .filter((block) => block.length > 0)
    .map((block) => `<p>${block.replace(/\n/g, '<br>')}</p>`);

  const html = paragraphs.length > 0
    ? paragraphs.join('')
    : `<p>${source.replace(/\n/g, '<br>')}</p>`;

  return sanitize(html);
}

export default renderInlineMarkdown;
