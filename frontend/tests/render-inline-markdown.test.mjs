import { renderInlineMarkdown } from '../utils/renderInlineMarkdown.js';

function identitySanitize(html) {
  return html;
}

function identityEscape(value) {
  return String(value);
}

function run() {
  const formatted = renderInlineMarkdown('**bold** _italic_ <u>underline</u> ~~strike~~', {
    sanitizeHTML: identitySanitize,
    escapeText: identityEscape,
    escapeAttr: identityEscape
  });

  if (!formatted.includes('<strong>bold</strong>')) {
    throw new Error('Expected strong tag to be present');
  }
  if (!formatted.includes('<em>italic</em>')) {
    throw new Error('Expected em tag to be present');
  }
  if (!formatted.includes('<u>underline</u>')) {
    throw new Error('Expected underline tag to be preserved');
  }
  if (!formatted.includes('<s>strike</s>')) {
    throw new Error('Expected strikethrough tag to be present');
  }

  if (!/^<p>/.test(formatted.trim())) {
    throw new Error('Expected output to be wrapped in a paragraph');
  }

  console.log('render-inline-markdown.test.mjs: PASS');
}

run();
