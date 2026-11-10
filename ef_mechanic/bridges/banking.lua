EFMechanic = EFMechanic or {}
EFMechanic.Banking = EFMechanic.Banking or {}

local Banking = EFMechanic.Banking

---Thin wrapper so invoice code never talks to a banking resource directly.
---@param source number
---@param amount number
---@return boolean
function Banking.ChargePlayer(source, amount)
    amount = tonumber(amount) or 0
    if amount <= 0 then
        return false
    end

    if Config.PreferBankPayment then
        if EFMechanic.Framework.RemoveMoney(source, 'bank', amount) then
            return true
        end
        return EFMechanic.Framework.RemoveMoney(source, 'cash', amount)
    end

    if EFMechanic.Framework.RemoveMoney(source, 'cash', amount) then
        return true
    end
    return EFMechanic.Framework.RemoveMoney(source, 'bank', amount)
end
