# Создать страницу "Все материалы" (all-materials)

Простой способ создать страницу-коллекцию через API.

Файл содержит примеры для PowerShell (Windows) и curl.

---

PowerShell (используйте в папке проекта):

```powershell
$body = @{
  title = 'Все материалы'
  slug = 'all-materials'
  type = 'collection'
  status = 'published'
  seoTitle = 'Все материалы - Healthcare Hacks Brazil'
  seoDescription = 'Полная коллекция гайдов и статей о здравоохранении в Бразилии'
  collectionConfig = @{
    sourceTypes = @('article','guide')
    sortBy = 'publishedAt'
    sortOrder = 'desc'
    sections = @(
      @{ title = 'Гайды'; sourceTypes = @('guide') },
      @{ title = 'Статьи из блога'; sourceTypes = @('article') }
    )
    cardImages = @{}
  }
} | ConvertTo-Json -Depth 6

Invoke-RestMethod -Uri 'http://localhost/api/pages' -Method Post -Body $body -ContentType 'application/json'
```

curl (bash):

```bash
curl -X POST 'http://localhost/api/pages' \
  -H 'Content-Type: application/json' \
  -d '{
    "title":"Все материалы",
    "slug":"all-materials",
    "type":"collection",
    "status":"published",
    "seoTitle":"Все материалы - Healthcare Hacks Brazil",
    "seoDescription":"Полная коллекция гайдов и статей о здравоохранении в Бразилии",
    "collectionConfig":{
      "sourceTypes":["article","guide"],
      "sortBy":"publishedAt",
      "sortOrder":"desc",
      "sections":[{"title":"Гайды","sourceTypes":["guide"]},{"title":"Статьи из блога","sourceTypes":["article"]}],
      "cardImages":{}
    }
  }'
```

---

После создания откройте в браузере `http://localhost/all-materials`.

Если backend работает в подкаталоге (например `/healthcare-cms-backend`), используйте соответствующий хост/path.
