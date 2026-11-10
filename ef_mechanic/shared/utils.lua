EFMechanic = EFMechanic or {}

---Normalize a vehicle plate for SQL keys and lookups.
---@param plate string|nil
---@return string
function EFMechanic.NormalizePlate(plate)
    if type(plate) ~= 'string' then
        return ''
    end

    return (plate:gsub('%s+', ''):upper())
end

---Safe locale lookup. Falls back to the key when missing.
---@param key string
---@param ... any
---@return string
function EFMechanic.T(key, ...)
    local locale = Config and Config.Locale or 'de'
    local pack = Locales and Locales[locale] or nil
    local fallback = Locales and Locales['en'] or nil
    local template = (pack and pack[key]) or (fallback and fallback[key]) or key

    if select('#', ...) > 0 then
        local ok, formatted = pcall(string.format, template, ...)
        if ok then
            return formatted
        end
    end

    return template
end
