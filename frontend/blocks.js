// Определения всех типов блоков для визуального редактора

export const blockDefinitions = [
    {
        type: 'main-screen',
        name: 'Main Screen',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm0-144a56,56,0,1,0,56,56A56.06,56.06,0,0,0,128,72Zm0,96a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z"/></svg>',
        description: 'Главный экран с фоновым изображением',
        category: 'hero',
        defaultData: {
            title: 'Медицина в Бразилии: <br> Ваш гид по сложной системе',
            text: 'Помогаю русскоязычным экспатам разобраться в системе SUS, частных страховках (planos de saúde), найти проверенных врачей и получить необходимое лечение.',
            backgroundImage: 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?q=80&w=2070&auto=format&fit=crop',
            buttonText: 'Записаться на консультацию',
            buttonLink: '#'
        },
        settings: [
            { name: 'title', label: 'Заголовок', type: 'textarea', hint: 'Можно использовать <br> для переноса строки' },
            { name: 'text', label: 'Текст', type: 'textarea' },
            { name: 'backgroundImage', label: 'Фоновое изображение (URL)', type: 'text' },
            { name: 'buttonText', label: 'Текст кнопки', type: 'text' },
            { name: 'buttonLink', label: 'Ссылка кнопки', type: 'text' }
        ]
    },
    {
        type: 'page-header',
        name: 'Page Header',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"/></svg>',
        description: 'Заголовок подстраницы',
        category: 'headers',
        defaultData: {
            title: 'Полезные гайды',
            subtitle: 'Пошаговые инструкции и проверенные алгоритмы для решения ваших медицинских задач в Бразилии.'
        },
        settings: [
            { name: 'title', label: 'Заголовок (H2)', type: 'text' },
            { name: 'subtitle', label: 'Подзаголовок', type: 'textarea' }
        ]
    },
    {
        type: 'service-cards',
        name: 'Service Cards',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M216,48H40A16,16,0,0,0,24,64V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V64A16,16,0,0,0,216,48Zm0,144H40V64H216V192ZM184,96a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,96Zm0,32a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,128Z"/></svg>',
        description: 'Карточки услуг с иконками',
        category: 'content',
        defaultData: {
            title: 'Чем я могу помочь',
            subtitle: 'Моя цель — сэкономить ваше время, деньги и нервы, опираясь на личный опыт прохождения всех этапов медицинской бюрократии в Бразилии.',
            columns: 2,
            cards: [
                {
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#008d8d" viewBox="0 0 256 256"><path d="M208,24H72A16,16,0,0,0,56,40V224a8,8,0,0,0,11.58,7.16L80,225.05l12.42,6.11a8,8,0,0,0,7.16,0L112,225.05l12.42,6.11a8,8,0,0,0,7.16,0L144,225.05l12.42,6.11a8,8,0,0,0,7.16,0L176,225.05l12.42,6.11A8,8,0,0,0,200,224V40A16,16,0,0,0,208,24ZM184,212.95l-4.42-2.11a8,8,0,0,0-7.16,0L160,216.95l-12.42-6.11a8,8,0,0,0-7.16,0L128,216.95l-12.42-6.11a8,8,0,0,0-7.16,0L96,216.95l-12.42-6.11a8,8,0,0,0-7.16,0L72,212.95V40H184Z"/><rect x="88" y="80" width="80" height="6" rx="3"/><rect x="88" y="100" width="64" height="6" rx="3"/><rect x="88" y="120" width="72" height="6" rx="3"/><rect x="88" y="140" width="56" height="6" rx="3"/><rect x="88" y="160" width="76" height="6" rx="3"/><rect x="88" y="180" width="48" height="6" rx="3"/></svg>',
                    title: 'Понятные гайды',
                    text: 'Пошаговые инструкции, которые проведут вас через все этапы: от получения CPF до записи к узкому специалисту.'
                },
                {
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#008d8d" viewBox="0 0 256 256"><path d="M178,32c-20.65,0-38.73,8.88-50,23.89C116.73,40.88,98.65,32,78,32A62.07,62.07,0,0,0,16,94c0,70,103.79,126.66,108.21,129a8,8,0,0,0,7.58,0C136.21,220.66,240,164,240,94A62.07,62.07,0,0,0,178,32ZM128,206.8C109.74,196.16,32,147.69,32,94A46.06,46.06,0,0,1,78,48c19.45,0,35.78,10.36,42.6,27a8,8,0,0,0,14.8,0c6.82-16.67,23.15-27,42.6-27a46.06,46.06,0,0,1,46,46C224,147.69,146.26,196.16,128,206.8Z"/></svg>',
                    title: 'Личная поддержка',
                    text: 'Персональные консультации, где мы разберем именно вашу ситуацию и составим индивидуальный план действий.'
                }
            ]
        },
        settings: [
            { name: 'title', label: 'Заголовок секции', type: 'text' },
            { name: 'subtitle', label: 'Подзаголовок', type: 'textarea' },
            { name: 'columns', label: 'Количество колонок', type: 'select', options: [2, 3] },
            { name: 'cards', label: 'Карточки', type: 'array', itemFields: [
                { name: 'icon', label: 'SVG код иконки', type: 'textarea' },
                { name: 'title', label: 'Заголовок', type: 'text' },
                { name: 'text', label: 'Описание', type: 'textarea' }
            ]}
        ]
    },
    {
        type: 'article-cards',
        name: 'Article Cards',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,160H40V56H216V200ZM184,96a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,96Zm0,32a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,128Zm0,32a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,160Z"/></svg>',
        description: 'Карточки статей/гайдов с изображениями',
        category: 'content',
        defaultData: {
            title: '',
            columns: 3,
            cards: [
                {
                    image: 'https://images.unsplash.com/photo-1516549655169-df83a0774514?q=80&w=2070&auto=format&fit=crop',
                    title: 'Полный гайд по SUS для экспата',
                    text: 'Пошаговая инструкция, как зарегистрироваться в государственной системе, чего ожидать и как пользоваться ее возможностями...',
                    link: '#'
                },
                {
                    image: 'https://images.unsplash.com/photo-1551076805-e1869033e561?q=80&w=2070&auto=format&fit=crop',
                    title: 'Как выбрать частную страховку',
                    text: 'Разбираем типы страховок (Plano de Saúde), важные пункты в договоре и на что обратить внимание при выборе компании.',
                    link: '#'
                },
                {
                    image: 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?q=80&w=2070&auto=format&fit=crop',
                    title: 'Аналоги лекарств в Бразилии',
                    text: 'Полезные сервисы для поиска дженериков, а также особенности рецептов и покупки медикаментов в местных аптеках.',
                    link: '#'
                }
            ]
        },
        settings: [
            { name: 'title', label: 'Заголовок секции (опционально)', type: 'text' },
            { name: 'columns', label: 'Количество колонок', type: 'select', options: [2, 3] },
            { name: 'cards', label: 'Карточки', type: 'array', itemFields: [
                { name: 'image', label: 'Изображение (URL)', type: 'text' },
                { name: 'title', label: 'Заголовок', type: 'text' },
                { name: 'text', label: 'Описание', type: 'textarea' },
                { name: 'link', label: 'Ссылка', type: 'text' }
            ]}
        ]
    },
    {
        type: 'about-section',
        name: 'About Section',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>',
        description: 'Секция "О себе" с фото',
        category: 'content',
        defaultData: {
            image: 'https://placehold.co/600x720/E9EAF2/032A49?text=Anna+L.',
            title: 'Привет, я Анна Лютенко!',
            paragraphs: [
                'Я переехала в Бразилию несколько лет назад и, как человек с хроническим заболеванием, сразу с головой окунулась в местную медицинскую систему. Я прошла путь от полного непонимания до уверенной навигации по государственным программам и частным клиникам.',
                'Я создала этот проект, чтобы поделиться своим опытом и помочь вам избежать моих ошибок, сэкономив ваше время, деньги и, самое главное, нервы.'
            ]
        },
        settings: [
            { name: 'image', label: 'Изображение (URL)', type: 'text' },
            { name: 'title', label: 'Заголовок', type: 'text' },
            { name: 'paragraphs', label: 'Параграфы', type: 'array', itemFields: [
                { name: 'text', label: 'Текст параграфа', type: 'textarea' }
            ]}
        ]
    },
    {
        type: 'text-block',
        name: 'Text Block',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32Zm0,176H48V48H208V208ZM72,96a8,8,0,0,1,8-8h96a8,8,0,0,1,0,16H80A8,8,0,0,1,72,96Zm0,32a8,8,0,0,1,8-8h96a8,8,0,0,1,0,16H80A8,8,0,0,1,72,128Zm0,32a8,8,0,0,1,8-8h96a8,8,0,0,1,0,16H80A8,8,0,0,1,72,160Z"/></svg>',
        description: 'Блок текста с заголовком',
        category: 'content',
        defaultData: {
            title: '',
            content: 'Введите текст...',
            alignment: 'left',
            containerStyle: 'normal'
        },
        settings: [
            { name: 'title', label: 'Заголовок (H2, опционально)', type: 'text' },
            { name: 'content', label: 'Текст', type: 'textarea', hint: 'Поддерживается HTML' },
            { name: 'alignment', label: 'Выравнивание', type: 'select', options: ['left', 'center', 'right'] },
            { name: 'containerStyle', label: 'Стиль контейнера', type: 'select', options: [
                { value: 'normal', label: 'Обычный (max-width: 1100px)' },
                { value: 'article', label: 'Статья (max-width: 800px)' }
            ]}
        ]
    },
    {
        type: 'image-block',
        name: 'Image Block',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,16V158.75l-26.07-26.06a16,16,0,0,0-22.63,0l-20,20-44-44a16,16,0,0,0-22.62,0L40,149.37V56ZM40,172l52-52,80,80H40Zm176,28H194.63l-36-36,20-20L216,181.38V200ZM144,100a12,12,0,1,1,12,12A12,12,0,0,1,144,100Z"/></svg>',
        description: 'Изображение с выравниванием',
        category: 'content',
        defaultData: {
            url: 'https://images.unsplash.com/photo-1538108149393-fbbd81895907?q=80&w=2128&auto=format&fit=crop',
            alt: 'Описание изображения',
            caption: '',
            alignment: 'center',
            width: '100%',
            borderRadius: '12px'
        },
        settings: [
            { name: 'url', label: 'URL изображения', type: 'text' },
            { name: 'alt', label: 'Alt текст', type: 'text' },
            { name: 'caption', label: 'Подпись (опционально)', type: 'text' },
            { name: 'alignment', label: 'Выравнивание', type: 'select', options: [
                { value: 'center', label: 'По центру' },
                { value: 'float-left', label: 'Обтекание слева (float left)' },
                { value: 'float-right', label: 'Обтекание справа (float right)' }
            ]},
            { name: 'width', label: 'Ширина (для float)', type: 'select', options: ['30%', '40%', '45%', '50%', '100%'] },
            { name: 'borderRadius', label: 'Скругление углов', type: 'select', options: ['0px', '8px', '12px'] }
        ]
    },
    {
        type: 'blockquote',
        name: 'Blockquote',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M100,56H40A16,16,0,0,0,24,72v64a16,16,0,0,0,16,16h60v8a32,32,0,0,1-32,32,8,8,0,0,0,0,16,48.05,48.05,0,0,0,48-48V72A16,16,0,0,0,100,56Zm0,80H40V72h60Zm116-80H156a16,16,0,0,0-16,16v64a16,16,0,0,0,16,16h60v8a32,32,0,0,1-32,32,8,8,0,0,0,0,16,48.05,48.05,0,0,0,48-48V72A16,16,0,0,0,216,56Zm0,80H156V72h60Z"/></svg>',
        description: 'Цитата с акцентом',
        category: 'content',
        defaultData: {
            text: '"Главное, что нужно понять о SUS: это ваше право. Не важно, какой у вас тип визы или доход, если вы легально находитесь в стране, вы имеете право на бесплатное медицинское обслуживание."'
        },
        settings: [
            { name: 'text', label: 'Текст цитаты', type: 'textarea' }
        ]
    },
    {
        type: 'button',
        name: 'Button',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm40-88a8,8,0,0,1-8,8H128v32a8,8,0,0,1-16,0V136H80a8,8,0,0,1,0-16h32V88a8,8,0,0,1,16,0v32h32A8,8,0,0,1,168,128Z"/></svg>',
        description: 'Кнопка с ссылкой',
        category: 'content',
        defaultData: {
            text: 'Все гайды',
            link: '#',
            alignment: 'center',
            style: 'primary'
        },
        settings: [
            { name: 'text', label: 'Текст кнопки', type: 'text' },
            { name: 'link', label: 'Ссылка', type: 'text' },
            { name: 'alignment', label: 'Выравнивание', type: 'select', options: ['left', 'center', 'right'] },
            { name: 'style', label: 'Стиль', type: 'select', options: [
                { value: 'primary', label: 'Основная (teal)' }
            ]}
        ]
    },
    {
        type: 'section-title',
        name: 'Section Title',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M247.31,124.76c-.35-.79-8.82-19.58-27.65-38.41C194.57,61.26,162.88,48,128,48S61.43,61.26,36.34,86.35C17.51,105.18,9,124,8.69,124.76a8,8,0,0,0,0,6.5c.35.79,8.82,19.57,27.65,38.4C61.43,194.74,93.12,208,128,208s66.57-13.26,91.66-38.34c18.83-18.83,27.3-37.61,27.65-38.4A8,8,0,0,0,247.31,124.76ZM128,192c-30.78,0-57.67-11.19-79.93-33.25A133.47,133.47,0,0,1,25,128,133.33,133.33,0,0,1,48.07,97.25C70.33,75.19,97.22,64,128,64s57.67,11.19,79.93,33.25A133.46,133.46,0,0,1,231.05,128C223.84,141.46,192.43,192,128,192Zm0-112a48,48,0,1,0,48,48A48.05,48.05,0,0,0,128,80Zm0,80a32,32,0,1,1,32-32A32,32,0,0,1,128,160Z"/></svg>',
        description: 'H3 заголовок секции',
        category: 'content',
        defaultData: {
            text: 'Гайды',
            alignment: 'left'
        },
        settings: [
            { name: 'text', label: 'Текст заголовка', type: 'text' },
            { name: 'alignment', label: 'Выравнивание', type: 'select', options: ['left', 'center', 'right'] }
        ]
    },
    {
        type: 'section-divider',
        name: 'Section Divider',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128Z"/></svg>',
        description: 'Разделитель между секциями',
        category: 'layout',
        defaultData: {},
        settings: []
    },
    {
        type: 'chat-bot',
        name: 'Chat Bot',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M200,48H136V16a8,8,0,0,0-16,0V48H56A32,32,0,0,0,24,80V192a32,32,0,0,0,32,32H200a32,32,0,0,0,32-32V80A32,32,0,0,0,200,48Zm16,144a16,16,0,0,1-16,16H56a16,16,0,0,1-16-16V80A16,16,0,0,1,56,64H200a16,16,0,0,1,16,16Zm-84-76a12,12,0,1,1-12-12A12,12,0,0,1,132,116Zm44,0a12,12,0,1,1-12-12A12,12,0,0,1,176,116Zm-88,0a12,12,0,1,1-12-12A12,12,0,0,1,88,116Z"/></svg>',
        description: 'Рамка для интеграции кастомного AI-бота',
        category: 'interactive',
        defaultData: {
            placeholder: 'Введите ваш вопрос...',
            buttonText: '→'
        },
        settings: [
            { name: 'placeholder', label: 'Placeholder поля ввода', type: 'text' },
            { name: 'buttonText', label: 'Текст кнопки отправки', type: 'text' }
        ]
    },
    {
        type: 'spacer',
        name: 'Spacer',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M224,48V208a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V48A16,16,0,0,1,48,32H208A16,16,0,0,1,224,48ZM56,136a8,8,0,0,0,0,16h64v40a8,8,0,0,0,16,0V152h64a8,8,0,0,0,0-16H136V96h64a8,8,0,0,0,0-16H136V64a8,8,0,0,0-16,0V80H56a8,8,0,0,0,0,16h64v40Z"/></svg>',
        description: 'Пустое пространство',
        category: 'layout',
        defaultData: {
            height: 60
        },
        settings: [
            { name: 'height', label: 'Высота (px)', type: 'number' }
        ]
    }
];
