EFMechanic = EFMechanic or {}
EFMechanic.Inventory = EFMechanic.Inventory or {}

local Inventory = EFMechanic.Inventory

local function useOx()
    return GetResourceState('ox_inventory') == 'started'
end

---@param source number
---@param itemName string
---@param count number|nil
---@return boolean
function Inventory.CanCarry(source, itemName, count)
    count = count or 1
    if not useOx() then
        return false
    end
    return exports.ox_inventory:CanCarryItem(source, itemName, count) == true
end

---@param source number
---@param itemName string
---@param count number|nil
---@param metadata table|nil
---@return boolean
function Inventory.AddItem(source, itemName, count, metadata)
    count = count or 1
    if not useOx() then
        return false
    end
    return exports.ox_inventory:AddItem(source, itemName, count, metadata) == true
end

---@param source number
---@param itemName string
---@param count number|nil
---@param metadata table|nil
---@param slot number|nil
---@return boolean
function Inventory.RemoveItem(source, itemName, count, metadata, slot)
    count = count or 1
    if not useOx() then
        return false
    end
    return exports.ox_inventory:RemoveItem(source, itemName, count, metadata, slot) == true
end

---@param source number
---@param itemName string
---@return number
function Inventory.GetItemCount(source, itemName)
    if not useOx() then
        return 0
    end
    return exports.ox_inventory:GetItemCount(source, itemName) or 0
end
