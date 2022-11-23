require('LuaUtils')
require('RedisTable')

-- luacheck: std lua51, globals redis RedisTable KEYS ARGV
local tableName = KEYS[1]
local checksumLength = KEYS[2]
local key = KEYS[3]
local keyProperties = KEYS[4]
local row = ARGV[1]

return RedisTable.new(tableName, checksumLength, keyProperties).setRawRow(key, row)
