#!/bin/bash

# Скрипт для создания тестовой страницы через API

API_URL="http://localhost/healthcare-backend/public/api"

# Получить токен (замените на ваши данные)
echo "Получение токена..."
TOKEN_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"anna","password":"test123"}')

TOKEN=$(echo $TOKEN_RESPONSE | grep -oP '"token":"\K[^"]+')
USER_ID=$(echo $TOKEN_RESPONSE | grep -oP '"id":"\K[^"]+')

if [ -z "$TOKEN" ]; then
    echo "Ошибка: Не удалось получить токен"
    echo "Ответ: $TOKEN_RESPONSE"
    exit 1
fi

echo "Токен получен: $TOKEN"
echo "User ID: $USER_ID"

# Создать страницу
echo ""
echo "Создание страницы..."

PAGE_DATA='{
  "title": "Новая тестовая страница",
  "slug": "test-page-'$(date +%s)'",
  "type": "regular",
  "createdBy": "'$USER_ID'",
  "seoTitle": "Тестовая страница",
  "seoDescription": "Описание тестовой страницы",
  "blocks": [
    {
      "type": "main-screen",
      "position": 0,
      "data": {
        "title": "Тестовая страница",
        "subtitle": "Создана через API скрипт"
      }
    },
    {
      "type": "text-block",
      "position": 1,
      "data": {
        "content": "<p>Это тестовая страница, созданная через API.</p>"
      }
    }
  ]
}'

CREATE_RESPONSE=$(curl -s -X POST "$API_URL/pages" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$PAGE_DATA")

echo "Ответ API:"
echo $CREATE_RESPONSE | python -m json.tool 2>/dev/null || echo $CREATE_RESPONSE

# Проверить успешность
if echo "$CREATE_RESPONSE" | grep -q '"success":true'; then
    echo ""
    echo "✅ Страница успешно создана!"
    PAGE_ID=$(echo $CREATE_RESPONSE | grep -oP '"pageId":"\K[^"]+')
    echo "ID страницы: $PAGE_ID"
    echo "Редактировать: http://localhost/visual-editor-standalone/index.html?id=$PAGE_ID"
else
    echo ""
    echo "❌ Ошибка создания страницы"
fi
