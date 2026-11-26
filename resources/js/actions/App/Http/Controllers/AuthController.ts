import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AuthController::tma
* @see app/Http/Controllers/AuthController.php:37
* @route '/api/auth/tma'
*/
export const tma = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tma.url(options),
    method: 'post',
})

tma.definition = {
    methods: ["post"],
    url: '/api/auth/tma',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AuthController::tma
* @see app/Http/Controllers/AuthController.php:37
* @route '/api/auth/tma'
*/
tma.url = (options?: RouteQueryOptions) => {
    return tma.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::tma
* @see app/Http/Controllers/AuthController.php:37
* @route '/api/auth/tma'
*/
tma.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tma.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AuthController::me
* @see app/Http/Controllers/AuthController.php:169
* @route '/api/auth/me'
*/
export const me = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: me.url(options),
    method: 'get',
})

me.definition = {
    methods: ["get","head"],
    url: '/api/auth/me',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AuthController::me
* @see app/Http/Controllers/AuthController.php:169
* @route '/api/auth/me'
*/
me.url = (options?: RouteQueryOptions) => {
    return me.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::me
* @see app/Http/Controllers/AuthController.php:169
* @route '/api/auth/me'
*/
me.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: me.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AuthController::me
* @see app/Http/Controllers/AuthController.php:169
* @route '/api/auth/me'
*/
me.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: me.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AuthController::syncSession
* @see app/Http/Controllers/AuthController.php:187
* @route '/api/auth/session/sync'
*/
export const syncSession = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncSession.url(options),
    method: 'post',
})

syncSession.definition = {
    methods: ["post"],
    url: '/api/auth/session/sync',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AuthController::syncSession
* @see app/Http/Controllers/AuthController.php:187
* @route '/api/auth/session/sync'
*/
syncSession.url = (options?: RouteQueryOptions) => {
    return syncSession.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::syncSession
* @see app/Http/Controllers/AuthController.php:187
* @route '/api/auth/session/sync'
*/
syncSession.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncSession.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AuthController::createDesktopLink
* @see app/Http/Controllers/AuthController.php:217
* @route '/api/auth/desktop-link'
*/
export const createDesktopLink = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createDesktopLink.url(options),
    method: 'post',
})

createDesktopLink.definition = {
    methods: ["post"],
    url: '/api/auth/desktop-link',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AuthController::createDesktopLink
* @see app/Http/Controllers/AuthController.php:217
* @route '/api/auth/desktop-link'
*/
createDesktopLink.url = (options?: RouteQueryOptions) => {
    return createDesktopLink.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::createDesktopLink
* @see app/Http/Controllers/AuthController.php:217
* @route '/api/auth/desktop-link'
*/
createDesktopLink.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createDesktopLink.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AuthController::logout
* @see app/Http/Controllers/AuthController.php:145
* @route '/api/auth/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/api/auth/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AuthController::logout
* @see app/Http/Controllers/AuthController.php:145
* @route '/api/auth/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::logout
* @see app/Http/Controllers/AuthController.php:145
* @route '/api/auth/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AuthController::desktopLogin
* @see app/Http/Controllers/AuthController.php:251
* @route '/tma/login/desktop'
*/
export const desktopLogin = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: desktopLogin.url(options),
    method: 'get',
})

desktopLogin.definition = {
    methods: ["get","head"],
    url: '/tma/login/desktop',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AuthController::desktopLogin
* @see app/Http/Controllers/AuthController.php:251
* @route '/tma/login/desktop'
*/
desktopLogin.url = (options?: RouteQueryOptions) => {
    return desktopLogin.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuthController::desktopLogin
* @see app/Http/Controllers/AuthController.php:251
* @route '/tma/login/desktop'
*/
desktopLogin.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: desktopLogin.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AuthController::desktopLogin
* @see app/Http/Controllers/AuthController.php:251
* @route '/tma/login/desktop'
*/
desktopLogin.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: desktopLogin.url(options),
    method: 'head',
})

const AuthController = { tma, me, syncSession, createDesktopLink, logout, desktopLogin }

export default AuthController