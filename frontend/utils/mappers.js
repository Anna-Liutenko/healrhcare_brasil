/**
 * Utility helpers for converting Vue reactive objects to plain structures
 * and mapping block payloads between frontend (camelCase) and API (snake_case).
 */

/**
 * Cyrillic to Latin transliteration dictionary.
 * Includes both lowercase and uppercase characters; characters missing from the
 * map are returned as-is.
 * @type {Record<string, string>}
 */
const CYRILLIC_MAP = {
  а: "a",
  б: "b",
  в: "v",
  г: "g",
  д: "d",
  е: "e",
  ё: "e",
  ж: "zh",
  з: "z",
  и: "i",
  й: "y",
  к: "k",
  л: "l",
  м: "m",
  н: "n",
  о: "o",
  п: "p",
  р: "r",
  с: "s",
  т: "t",
  у: "u",
  ф: "f",
  х: "h",
  ц: "ts",
  ч: "ch",
  ш: "sh",
  щ: "sch",
  ъ: "",
  ы: "y",
  ь: "",
  э: "e",
  ю: "yu",
  я: "ya",
  А: "A",
  Б: "B",
  В: "V",
  Г: "G",
  Д: "D",
  Е: "E",
  Ё: "E",
  Ж: "Zh",
  З: "Z",
  И: "I",
  Й: "Y",
  К: "K",
  Л: "L",
  М: "M",
  Н: "N",
  О: "O",
  П: "P",
  Р: "R",
  С: "S",
  Т: "T",
  У: "U",
  Ф: "F",
  Х: "H",
  Ц: "Ts",
  Ч: "Ch",
  Ш: "Sh",
  Щ: "Sch",
  Ъ: "",
  Ы: "Y",
  Ь: "",
  Э: "E",
  Ю: "Yu",
  Я: "Ya",
};

/**
 * Checks whether a value is a plain object (created via object literal or Object constructor).
 * @param {*} value
 * @returns {boolean}
 */
function isPlainObject(value) {
  if (value === null || typeof value !== "object") {
    return false;
  }

  const proto = Object.getPrototypeOf(value);
  return proto === Object.prototype || proto === null;
}

/**
 * Recursively converts Vue reactive objects (Proxy) and other complex structures
 * into plain JavaScript objects/arrays. Primitives are returned as-is.
 * Dates are cloned to prevent accidental mutation.
 * @template T
 * @param {T} input
 * @returns {T}
 */
export function toPlainObject(input) {
  if (input === null || input === undefined) {
    return input;
  }

  if (typeof input !== "object") {
    return input;
  }

  if (input instanceof Date) {
    return new Date(input.getTime());
  }

  if (Array.isArray(input)) {
    return /** @type {T} */ (
      input.map((item) => toPlainObject(item))
    );
  }

  if (!isPlainObject(input)) {
    // For unsupported object types (Map, Set, custom classes) fallback to shallow copy.
    return /** @type {T} */ ({ ...input });
  }

  return /** @type {T} */ (
    Object.keys(input).reduce((acc, key) => {
      acc[key] = toPlainObject(input[key]);
      return acc;
    }, /** @type {Record<string, unknown>} */ ({}))
  );
}

/**
 * Converts snake_case keys to camelCase.
 * @param {string} key
 * @returns {string}
 */
function snakeToCamel(key) {
  return key.replace(/_([a-z])/g, (_, char) => char.toUpperCase());
}

/**
 * Converts camelCase keys to snake_case.
 * @param {string} key
 * @returns {string}
 */
function camelToSnake(key) {
  return key
    .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
    .replace(/([A-Z])([A-Z][a-z])/g, "$1_$2")
    .toLowerCase();
}

/**
 * Helper that converts object keys using a provided converter function.
 * Nested objects/arrays are processed recursively; the `data` field of a block is
 * copied without key conversion to avoid unintended mutations of block payloads.
 * @param {Record<string, unknown>} source
 * @param {(key: string) => string} converter
 * @returns {Record<string, unknown>}
 */
function convertObjectKeys(source, converter) {
  const result = {};

  for (const [key, value] of Object.entries(source)) {
    if (key === "data") {
      result["data"] = toPlainObject(value ?? {});
      continue;
    }

    const convertedKey = converter(key);

    if (Array.isArray(value)) {
      result[convertedKey] = value.map((item) =>
        typeof item === "object" && item !== null
          ? convertObjectKeys(toPlainObject(item), converter)
          : toPlainObject(item)
      );
      continue;
    }

    if (value !== null && typeof value === "object") {
      result[convertedKey] = convertObjectKeys(toPlainObject(value), converter);
      continue;
    }

    result[convertedKey] = value;
  }

  return result;
}

/**
 * Converts a block object from camelCase (frontend) into the snake_case format expected by the API.
 * Vue proxies are flattened, arrays/objects are deeply cloned.
 * @param {Record<string, unknown>} block
 * @returns {Record<string, unknown>}
 */
export function blockToAPI(block) {
  if (!block || typeof block !== "object") {
    throw new TypeError("blockToAPI expects a block object");
  }

  const plainBlock = toPlainObject(block);
  const converted = convertObjectKeys(plainBlock, camelToSnake);

  // Ensure defaults for critical fields.
  if (!("editable_fields" in converted)) {
    converted.editable_fields = [];
  }

  if (!("is_editable" in converted)) {
    converted.is_editable = Boolean(plainBlock.isEditable);
  }

  // Remove undefined values to avoid sending extraneous fields.
  for (const key of Object.keys(converted)) {
    if (converted[key] === undefined) {
      delete converted[key];
    }
  }

  return converted;
}

/**
 * Converts a block object from API snake_case format into the camelCase structure used on the frontend.
 * Ensures arrays/objects are deeply cloned and default values are applied where это необходимо.
 * @param {Record<string, unknown>} apiBlock
 * @returns {Record<string, unknown>}
 */
export function blockFromAPI(apiBlock) {
  if (!apiBlock || typeof apiBlock !== "object") {
    throw new TypeError("blockFromAPI expects a block object");
  }

  const plainBlock = toPlainObject(apiBlock);
  const converted = convertObjectKeys(plainBlock, snakeToCamel);

  // Normalize known fields with sensible defaults.
  converted.editableFields = Array.isArray(converted.editableFields)
    ? [...converted.editableFields]
    : [];

  if (converted.position != null) {
    const numericPosition = Number(converted.position);
    converted.position = Number.isNaN(numericPosition)
      ? converted.position
      : numericPosition;
  }

  if (typeof converted.isEditable !== "boolean") {
    converted.isEditable = Boolean(converted.isEditable);
  }

  converted.data = converted.data ?? {};

  return converted;
}

/**
 * Transliterate Cyrillic symbols to Latin equivalents to build URL-friendly slugs.
 * Non-Cyrillic characters remain untouched.
 * @param {string | number | null | undefined} text
 * @returns {string}
 */
export function transliterate(text) {
  if (text === null || text === undefined) {
    return "";
  }

  return String(text)
    .split("")
    .map((char) => CYRILLIC_MAP[char] ?? char)
    .join("");
}

/**
 * Generates a URL-friendly slug from a title: transliteration → lower-case → cleanup.
 * @param {string | number | null | undefined} title
 * @returns {string}
 */
export function generateSlug(title) {
  const raw = transliterate(title)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/-{2,}/g, "-")
    .replace(/^-+|-+$/g, "");

  return raw;
}
