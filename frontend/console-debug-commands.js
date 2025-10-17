// ===== КОМАНДЫ ДЛЯ КОНСОЛИ БРАУЗЕРА (DevTools) =====
// Скопируйте эти команды в Console на странице editor.html?id=85218cf3-8430-40dd-8ea7-16b028aa5643

// 1. Проверить состояние Vue-приложения
console.log('=== VUE APP STATE ===');
console.log('currentUser:', app.currentUser);
console.log('currentPageId:', app.currentPageId);
console.log('isEditMode:', app.isEditMode);
console.log('blocks.length:', app.blocks.length);
console.log('blocks:', app.blocks);
console.log('pageData:', app.pageData);

// 2. Проверить что возвращает renderBlock для первого блока
if (app.blocks.length > 0) {
    console.log('=== RENDER TEST ===');
    console.log('First block:', app.blocks[0]);
    console.log('renderBlock output:', app.renderBlock(app.blocks[0]));
}

// 3. Проверить blockDefinitions
console.log('=== BLOCK DEFINITIONS ===');
console.log('blockDefinitions:', app.blockDefinitions);

// 4. Принудительно загрузить страницу
console.log('=== MANUAL LOAD TEST ===');
app.loadPageFromAPI('85218cf3-8430-40dd-8ea7-16b028aa5643').then(() => {
    console.log('Manual load complete');
    console.log('blocks after manual load:', app.blocks.length);
});

// 5. Проверить Debug Panel
console.log('=== DEBUG MESSAGES ===');
console.log(app.debugMessages.slice(-10)); // последние 10 сообщений
