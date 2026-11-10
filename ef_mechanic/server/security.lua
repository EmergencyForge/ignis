EFMechanic = EFMechanic or {}
EFMechanic.Security = EFMechanic.Security or {}

local Security = EFMechanic.Security

---@type table<number, table<string, number>>
local rateBuckets = {}

---Simple per-source rate limit. Returns false when blocked.
---@param source number
---@param action string
---@param windowMs number|nil
---@return boolean
function Security.Allow(source, action, windowMs)
    windowMs = windowMs or 750
    local now = GetGameTimer()
    rateBuckets[source] = rateBuckets[source] or {}
    local last = rateBuckets[source][action] or 0
    if now - last < windowMs then
        return false
    end
    rateBuckets[source][action] = now
    return true
end

AddEventHandler('playerDropped', function()
    rateBuckets[source] = nil
end)

---Distance helper for later milestones.
---@param a vector3
---@param b vector3
---@param maxDist number
---@return boolean
function Security.WithinDistance(a, b, maxDist)
    if not a or not b then
        return false
    end
    return #(a - b) <= maxDist
end
