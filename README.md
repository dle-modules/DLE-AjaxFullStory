# DLE-AjaxFullStory
![Release version](https://img.shields.io/github/v/release/dle-modules/DLE-AjaxFullStory?style=flat-square)
![DLE](https://img.shields.io/badge/DLE-14.x-green.svg?style=flat-square "DLE Version")
![License](https://img.shields.io/github/license/dle-modules/DLE-AjaxFullStory?style=flat-square)

Бесплатный модуль для загрузки полной новости в DLE средствами AJAX

## Требования
- Версия DLE: **14.x**

## Особенности модуля
- Не требует каких-либо правок движка
- Учёт прав доступа к новости
- Подсчёт количества просмотров (если это разрешено)
- Корректная очистка кеша модуля
- Поддержка всех тегов
- Кеширование на стороне клиента (модуль отдаёт правильные заголовки)

## Установка
- Устанавливаем как обычный плагин, файл **[afs_plugin.zip](https://github.com/dle-modules/DLE-AjaxFullStory/releases/latest)** содержит всё необходимое для автоматической установки.
- В нужном месте прописать стили и скрипты модуля (если у вас уже есть magnificpopup - второй раз прописывать не нужно)
```html
<link href="{THEME}/ajax/fullstory.css" type="text/css" rel="stylesheet">
<link href="{THEME}/ajax/magnificpopup.css" type="text/css" rel="stylesheet">
<script src="{THEME}/ajax/magnificpopup.js"></script>
<script src="{THEME}/ajax/fullstory.js"></script>
```
- В нужном месте любого шаблона вставить минимальный код:
``` html
<span data-afs-id="{news-id}">Быстрый просмотр</span>
```
где `{news-id}` - ID новости (**обязательный параметр**).
- Так же можно использовать дополнительные атрибуты:
    ``` html
    <span 
        data-afs-id="{news-id}" 
        data-afs-template="mytemplate" 
        data-afs-preset="mypreset"
    >Быстрый просмотр</span>
    ```
    + `data-afs-template="mytemplate"` - Путь к шаблону модуля относительно текущей папки с шаблоном сайта. Если на сайте разрешена смена скина, то путь будет построен относительно активного в данный момент шаблона сайта. По умолчанию: **{THEME}/ajax/fullstory**. (Необязательный параметр).
    + `data-afs-preset="mypreset"` - Путь к файлу с настройками модуля. По умолчанию не используется.
    Подробнее о параметрах читайте в документации.
    z
