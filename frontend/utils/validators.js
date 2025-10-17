/**
 * Валидационные утилиты для фронтенда CMS.
 * Каждая функция возвращает объект вида { valid: boolean, message: string | null }.
 */

/**
 * Базовая структура ответа валидатора.
 * @param {boolean} valid
 * @param {string | null} message
 * @returns {{ valid: boolean, message: string | null }}
 */
function result(valid, message = null) {
  return { valid, message };
}

/**
 * Проверяет slug на соответствие требованиям CMS:
 * - только строчные латинские буквы, цифры и дефис
 * - никакого двойного дефиса
 * - не начинается и не заканчивается дефисом
 * - длина от 1 до 255 символов
 * @param {string | null | undefined} slug
 * @returns {{ valid: boolean, message: string | null }}
 */
export function validateSlug(slug) {
  if (slug === null || slug === undefined || slug === "") {
    return result(false, "Slug не может быть пустым.");
  }

  if (slug.length > 255) {
    return result(false, "Slug не должен превышать 255 символов.");
  }

  if (!/^[a-z0-9-]+$/.test(slug)) {
    return result(false, "Slug может содержать только строчные латинские буквы, цифры и дефисы.");
  }

  if (slug.startsWith("-") || slug.endsWith("-")) {
    return result(false, "Slug не должен начинаться или заканчиваться дефисом.");
  }

  if (/--/.test(slug)) {
    return result(false, "Slug не должен содержать два и более дефиса подряд.");
  }

  return result(true, null);
}

/**
 * Простая проверка email-адреса. Поддерживает формат username@domain.tld.
 * Не претендует на сложную RFC-валидацию, но отсекает очевидные ошибки.
 * @param {string | null | undefined} email
 * @returns {{ valid: boolean, message: string | null }}
 */
export function validateEmail(email) {
  if (email === null || email === undefined || email === "") {
    return result(false, "Email обязателен.");
  }

  if (email.length > 255) {
    return result(false, "Email не должен превышать 255 символов.");
  }

  const pattern = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
  if (!pattern.test(email)) {
    return result(false, "Укажите корректный email в формате user@example.com.");
  }

  return result(true, null);
}

/**
 * Проверяет UUID v4 (строка вида 123e4567-e89b-12d3-a456-426614174000).
 * Требует строгий нижний регистр и корректные хекс-символы.
 * @param {string | null | undefined} uuid
 * @returns {{ valid: boolean, message: string | null }}
 */
export function validateUUID(uuid) {
  if (uuid === null || uuid === undefined || uuid === "") {
    return result(false, "UUID обязателен.");
  }

  const pattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
  if (!pattern.test(uuid)) {
    return result(false, "Ожидается UUID v4 в формате 123e4567-e89b-12d3-a456-426614174000.");
  }

  return result(true, null);
}

/**
 * Композиция валидаторов: запускает цепочку валидаторов последовательно и
 * возвращает первую ошибку. Если все проверки прошли, valid=true.
 * @param {...function(string | null | undefined): { valid: boolean, message: string | null }} validators
 * @returns {(value: string | null | undefined) => { valid: boolean, message: string | null }}
 */
export function composeValidators(...validators) {
  return (value) => {
    for (const validator of validators) {
      const outcome = validator(value);
      if (!outcome.valid) {
        return outcome;
      }
    }

    return result(true, null);
  };
}
