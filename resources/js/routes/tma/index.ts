import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../../wayfinder'
import audit4a8d1e from './audit'
import orders from './orders'
import customers from './customers'
import leads from './leads'
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

/**
* @see routes/web.php:38
* @route '/blocked'
*/
export const blocked = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: blocked.url(options),
    method: 'get',
})

blocked.definition = {
    methods: ["get","head"],
    url: '/blocked',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:38
* @route '/blocked'
*/
blocked.url = (options?: RouteQueryOptions) => {
    return blocked.definition.url + queryParams(options)
}

/**
* @see routes/web.php:38
* @route '/blocked'
*/
blocked.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: blocked.url(options),
    method: 'get',
})

/**
* @see routes/web.php:38
* @route '/blocked'
*/
blocked.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: blocked.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::home
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::home
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::home
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::home
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::personnel
* @see app/Http/Controllers/TmaController.php:81
* @route '/tma/personnel'
*/
export const personnel = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: personnel.url(options),
    method: 'get',
})

personnel.definition = {
    methods: ["get","head"],
    url: '/tma/personnel',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::personnel
* @see app/Http/Controllers/TmaController.php:81
* @route '/tma/personnel'
*/
personnel.url = (options?: RouteQueryOptions) => {
    return personnel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::personnel
* @see app/Http/Controllers/TmaController.php:81
* @route '/tma/personnel'
*/
personnel.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: personnel.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::personnel
* @see app/Http/Controllers/TmaController.php:81
* @route '/tma/personnel'
*/
personnel.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: personnel.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::audit
* @see app/Http/Controllers/TmaController.php:94
* @route '/tma/audit'
*/
export const audit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: audit.url(options),
    method: 'get',
})

audit.definition = {
    methods: ["get","head"],
    url: '/tma/audit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::audit
* @see app/Http/Controllers/TmaController.php:94
* @route '/tma/audit'
*/
audit.url = (options?: RouteQueryOptions) => {
    return audit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::audit
* @see app/Http/Controllers/TmaController.php:94
* @route '/tma/audit'
*/
audit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: audit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::audit
* @see app/Http/Controllers/TmaController.php:94
* @route '/tma/audit'
*/
audit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: audit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::settings
* @see app/Http/Controllers/TmaController.php:120
* @route '/tma/settings'
*/
export const settings = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(options),
    method: 'get',
})

settings.definition = {
    methods: ["get","head"],
    url: '/tma/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::settings
* @see app/Http/Controllers/TmaController.php:120
* @route '/tma/settings'
*/
settings.url = (options?: RouteQueryOptions) => {
    return settings.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::settings
* @see app/Http/Controllers/TmaController.php:120
* @route '/tma/settings'
*/
settings.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::settings
* @see app/Http/Controllers/TmaController.php:120
* @route '/tma/settings'
*/
settings.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: settings.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::sites
* @see app/Http/Controllers/TmaController.php:303
* @route '/tma/sites'
*/
export const sites = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sites.url(options),
    method: 'get',
})

sites.definition = {
    methods: ["get","head"],
    url: '/tma/sites',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::sites
* @see app/Http/Controllers/TmaController.php:303
* @route '/tma/sites'
*/
sites.url = (options?: RouteQueryOptions) => {
    return sites.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::sites
* @see app/Http/Controllers/TmaController.php:303
* @route '/tma/sites'
*/
sites.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sites.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::sites
* @see app/Http/Controllers/TmaController.php:303
* @route '/tma/sites'
*/
sites.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sites.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::financial
* @see app/Http/Controllers/TmaController.php:133
* @route '/tma/financial'
*/
export const financial = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: financial.url(options),
    method: 'get',
})

financial.definition = {
    methods: ["get","head"],
    url: '/tma/financial',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::financial
* @see app/Http/Controllers/TmaController.php:133
* @route '/tma/financial'
*/
financial.url = (options?: RouteQueryOptions) => {
    return financial.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::financial
* @see app/Http/Controllers/TmaController.php:133
* @route '/tma/financial'
*/
financial.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: financial.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::financial
* @see app/Http/Controllers/TmaController.php:133
* @route '/tma/financial'
*/
financial.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: financial.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::analytics
* @see app/Http/Controllers/TmaController.php:149
* @route '/tma/analytics'
*/
export const analytics = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analytics.url(options),
    method: 'get',
})

analytics.definition = {
    methods: ["get","head"],
    url: '/tma/analytics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::analytics
* @see app/Http/Controllers/TmaController.php:149
* @route '/tma/analytics'
*/
analytics.url = (options?: RouteQueryOptions) => {
    return analytics.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::analytics
* @see app/Http/Controllers/TmaController.php:149
* @route '/tma/analytics'
*/
analytics.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analytics.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::analytics
* @see app/Http/Controllers/TmaController.php:149
* @route '/tma/analytics'
*/
analytics.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: analytics.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::manager
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
export const manager = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manager.url(options),
    method: 'get',
})

manager.definition = {
    methods: ["get","head"],
    url: '/tma/manager',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::manager
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
manager.url = (options?: RouteQueryOptions) => {
    return manager.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::manager
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
manager.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manager.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::manager
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
manager.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: manager.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::role
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
export const role = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: role.url(args, options),
    method: 'get',
})

role.definition = {
    methods: ["get","head"],
    url: '/tma/{role?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::role
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
role.url = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { role: args }
    }

    if (Array.isArray(args)) {
        args = {
            role: args[0],
        }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
        "role",
    ])

    const parsedArgs = {
        role: args?.role,
    }

    return role.definition.url
            .replace('{role?}', parsedArgs.role?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::role
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
role.get = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: role.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::role
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
role.head = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: role.url(args, options),
    method: 'head',
})

const tma = {
    desktopLogin: Object.assign(desktopLogin, desktopLogin),
    blocked: Object.assign(blocked, blocked),
    home: Object.assign(home, home),
    personnel: Object.assign(personnel, personnel),
    audit: Object.assign(audit, audit4a8d1e),
    settings: Object.assign(settings, settings),
    sites: Object.assign(sites, sites),
    financial: Object.assign(financial, financial),
    analytics: Object.assign(analytics, analytics),
    orders: Object.assign(orders, orders),
    customers: Object.assign(customers, customers),
    leads: Object.assign(leads, leads),
    manager: Object.assign(manager, manager),
    role: Object.assign(role, role),
}

export default tma