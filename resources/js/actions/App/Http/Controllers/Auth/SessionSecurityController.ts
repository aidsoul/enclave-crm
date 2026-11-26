import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::confirmIp
* @see app/Http/Controllers/Auth/SessionSecurityController.php:133
* @route '/api/auth/confirm-ip'
*/
export const confirmIp = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmIp.url(options),
    method: 'post',
})

confirmIp.definition = {
    methods: ["post"],
    url: '/api/auth/confirm-ip',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::confirmIp
* @see app/Http/Controllers/Auth/SessionSecurityController.php:133
* @route '/api/auth/confirm-ip'
*/
confirmIp.url = (options?: RouteQueryOptions) => {
    return confirmIp.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::confirmIp
* @see app/Http/Controllers/Auth/SessionSecurityController.php:133
* @route '/api/auth/confirm-ip'
*/
confirmIp.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmIp.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::blockSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:110
* @route '/api/auth/block-session'
*/
export const blockSession = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: blockSession.url(options),
    method: 'post',
})

blockSession.definition = {
    methods: ["post"],
    url: '/api/auth/block-session',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::blockSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:110
* @route '/api/auth/block-session'
*/
blockSession.url = (options?: RouteQueryOptions) => {
    return blockSession.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::blockSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:110
* @route '/api/auth/block-session'
*/
blockSession.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: blockSession.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::sessions
* @see app/Http/Controllers/Auth/SessionSecurityController.php:29
* @route '/api/auth/sessions'
*/
export const sessions = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sessions.url(options),
    method: 'get',
})

sessions.definition = {
    methods: ["get","head"],
    url: '/api/auth/sessions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::sessions
* @see app/Http/Controllers/Auth/SessionSecurityController.php:29
* @route '/api/auth/sessions'
*/
sessions.url = (options?: RouteQueryOptions) => {
    return sessions.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::sessions
* @see app/Http/Controllers/Auth/SessionSecurityController.php:29
* @route '/api/auth/sessions'
*/
sessions.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sessions.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::sessions
* @see app/Http/Controllers/Auth/SessionSecurityController.php:29
* @route '/api/auth/sessions'
*/
sessions.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sessions.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::revokeSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:51
* @route '/api/auth/sessions'
*/
export const revokeSession = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: revokeSession.url(options),
    method: 'delete',
})

revokeSession.definition = {
    methods: ["delete"],
    url: '/api/auth/sessions',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::revokeSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:51
* @route '/api/auth/sessions'
*/
revokeSession.url = (options?: RouteQueryOptions) => {
    return revokeSession.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\SessionSecurityController::revokeSession
* @see app/Http/Controllers/Auth/SessionSecurityController.php:51
* @route '/api/auth/sessions'
*/
revokeSession.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: revokeSession.url(options),
    method: 'delete',
})

const SessionSecurityController = { confirmIp, blockSession, sessions, revokeSession }

export default SessionSecurityController