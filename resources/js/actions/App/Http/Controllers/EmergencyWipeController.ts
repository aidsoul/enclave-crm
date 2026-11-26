import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\EmergencyWipeController::status
* @see app/Http/Controllers/EmergencyWipeController.php:24
* @route '/api/emergency-wipe/status'
*/
export const status = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/api/emergency-wipe/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmergencyWipeController::status
* @see app/Http/Controllers/EmergencyWipeController.php:24
* @route '/api/emergency-wipe/status'
*/
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmergencyWipeController::status
* @see app/Http/Controllers/EmergencyWipeController.php:24
* @route '/api/emergency-wipe/status'
*/
status.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\EmergencyWipeController::status
* @see app/Http/Controllers/EmergencyWipeController.php:24
* @route '/api/emergency-wipe/status'
*/
status.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\EmergencyWipeController::setPin
* @see app/Http/Controllers/EmergencyWipeController.php:36
* @route '/api/emergency-wipe/pin'
*/
export const setPin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setPin.url(options),
    method: 'post',
})

setPin.definition = {
    methods: ["post"],
    url: '/api/emergency-wipe/pin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmergencyWipeController::setPin
* @see app/Http/Controllers/EmergencyWipeController.php:36
* @route '/api/emergency-wipe/pin'
*/
setPin.url = (options?: RouteQueryOptions) => {
    return setPin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmergencyWipeController::setPin
* @see app/Http/Controllers/EmergencyWipeController.php:36
* @route '/api/emergency-wipe/pin'
*/
setPin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setPin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmergencyWipeController::initiate
* @see app/Http/Controllers/EmergencyWipeController.php:55
* @route '/api/emergency-wipe/initiate'
*/
export const initiate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

initiate.definition = {
    methods: ["post"],
    url: '/api/emergency-wipe/initiate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmergencyWipeController::initiate
* @see app/Http/Controllers/EmergencyWipeController.php:55
* @route '/api/emergency-wipe/initiate'
*/
initiate.url = (options?: RouteQueryOptions) => {
    return initiate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmergencyWipeController::initiate
* @see app/Http/Controllers/EmergencyWipeController.php:55
* @route '/api/emergency-wipe/initiate'
*/
initiate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmergencyWipeController::execute
* @see app/Http/Controllers/EmergencyWipeController.php:75
* @route '/api/emergency-wipe/execute'
*/
export const execute = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

execute.definition = {
    methods: ["post"],
    url: '/api/emergency-wipe/execute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmergencyWipeController::execute
* @see app/Http/Controllers/EmergencyWipeController.php:75
* @route '/api/emergency-wipe/execute'
*/
execute.url = (options?: RouteQueryOptions) => {
    return execute.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmergencyWipeController::execute
* @see app/Http/Controllers/EmergencyWipeController.php:75
* @route '/api/emergency-wipe/execute'
*/
execute.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

const EmergencyWipeController = { status, setPin, initiate, execute }

export default EmergencyWipeController