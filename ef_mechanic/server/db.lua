EFMechanic = EFMechanic or {}
EFMechanic.Db = EFMechanic.Db or {}

local Db = EFMechanic.Db

---Run install.sql once on start (idempotent CREATE IF NOT EXISTS).
function Db.EnsureSchema()
    local sqlPath = ('%s/sql/install.sql'):format(GetResourcePath(GetCurrentResourceName()))
    local file = io.open(sqlPath, 'r')
    if not file then
        print('[ef_mechanic] WARNING: sql/install.sql not found')
        return
    end

    local content = file:read('*a')
    file:close()

    for statement in content:gmatch('([^;]+);') do
        local trimmed = statement:gsub('^%s+', ''):gsub('%s+$', '')
        if trimmed ~= '' and not trimmed:match('^%-%-') then
            MySQL.query(trimmed)
        end
    end

    if Config.Debug then
        print('[ef_mechanic] schema ensure finished')
    end
end
