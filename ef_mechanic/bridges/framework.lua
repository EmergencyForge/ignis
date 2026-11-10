EFMechanic = EFMechanic or {}
EFMechanic.Framework = EFMechanic.Framework or {}

local Framework = EFMechanic.Framework
local detected = nil ---@type 'esx'|'qb'|'qbox'|nil
local ESX = nil

local function detect()
    if detected ~= nil then
        return detected ~= false and detected or nil
    end

    if GetResourceState('es_extended') == 'started' then
        detected = 'esx'
        ESX = exports['es_extended']:getSharedObject()
        return detected
    end

    if GetResourceState('qbx_core') == 'started' then
        detected = 'qbox'
        return detected
    end

    if GetResourceState('qb-core') == 'started' then
        detected = 'qb'
        return detected
    end

    detected = false
    return nil
end

---@return string|nil
function Framework.GetName()
    return detect()
end


---@param source number
---@return table|nil
function Framework.GetPlayer(source)
    local name = detect()
    if name == 'esx' then
        if not ESX then
            ESX = exports['es_extended']:getSharedObject()
        end
        return ESX.GetPlayerFromId(source)
    end

    if name == 'qb' or name == 'qbox' then
        -- Stub: same signature reserved for later milestones.
        if Config.Debug then
            print(('[ef_mechanic] Framework.GetPlayer stub for %s'):format(name))
        end
        return nil
    end

    return nil
end

---@param source number
---@return string|nil
function Framework.GetIdentifier(source)
    local name = detect()
    if name == 'esx' then
        local player = Framework.GetPlayer(source)
        return player and player.identifier or nil
    end

    return nil
end

---@param source number
---@return string|nil jobName
---@return number grade
function Framework.GetJob(source)
    local name = detect()
    if name == 'esx' then
        local player = Framework.GetPlayer(source)
        if not player or not player.job then
            return nil, 0
        end
        return player.job.name, player.job.grade or 0
    end

    return nil, 0
end

---@param source number
---@param jobName string
---@param minGrade number|nil
---@return boolean
function Framework.HasJob(source, jobName, minGrade)
    local name, grade = Framework.GetJob(source)
    if not name or name ~= jobName then
        return false
    end
    return grade >= (minGrade or 0)
end

---@param source number
---@param account 'cash'|'bank'
---@return number
function Framework.GetMoney(source, account)
    local name = detect()
    if name == 'esx' then
        local player = Framework.GetPlayer(source)
        if not player then
            return 0
        end
        if account == 'cash' then
            return player.getMoney() or 0
        end
        local bank = player.getAccount('bank')
        return bank and bank.money or 0
    end

    return 0
end

---@param source number
---@param account 'cash'|'bank'
---@param amount number
---@return boolean
function Framework.RemoveMoney(source, account, amount)
    amount = tonumber(amount) or 0
    if amount <= 0 then
        return false
    end

    local name = detect()
    if name == 'esx' then
        local player = Framework.GetPlayer(source)
        if not player then
            return false
        end
        if account == 'cash' then
            if (player.getMoney() or 0) < amount then
                return false
            end
            player.removeMoney(amount)
            return true
        end
        local bank = player.getAccount('bank')
        if not bank or (bank.money or 0) < amount then
            return false
        end
        player.removeAccountMoney('bank', amount)
        return true
    end

    return false
end

---@param source number
---@param account 'cash'|'bank'
---@param amount number
---@return boolean
function Framework.AddMoney(source, account, amount)
    amount = tonumber(amount) or 0
    if amount <= 0 then
        return false
    end

    local name = detect()
    if name == 'esx' then
        local player = Framework.GetPlayer(source)
        if not player then
            return false
        end
        if account == 'cash' then
            player.addMoney(amount)
            return true
        end
        player.addAccountMoney('bank', amount)
        return true
    end

    return false
end

---Placeholder society helpers — filled in Milestone 4.
---@param accountName string
---@return number
function Framework.GetSocietyBalance(accountName)
    local name = detect()
    if name == 'esx' and Config.SocietyProvider == 'esx_addonaccount' then
        -- Implemented when invoices land; keep signature stable.
        return 0
    end
    return 0
end

---@param accountName string
---@param amount number
---@return boolean
function Framework.AddSocietyMoney(accountName, amount)
    return false
end

---@param accountName string
---@param amount number
---@return boolean
function Framework.RemoveSocietyMoney(accountName, amount)
    return false
end
