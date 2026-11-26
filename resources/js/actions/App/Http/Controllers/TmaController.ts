import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
const index980bb49ee7ae63891f1d891d2fbcf1c9 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'get',
})

index980bb49ee7ae63891f1d891d2fbcf1c9.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
index980bb49ee7ae63891f1d891d2fbcf1c9.url = (options?: RouteQueryOptions) => {
    return index980bb49ee7ae63891f1d891d2fbcf1c9.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
index980bb49ee7ae63891f1d891d2fbcf1c9.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/'
*/
index980bb49ee7ae63891f1d891d2fbcf1c9.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
const index3a82120d0f92a60f9f51bb1bc1a80e34 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index3a82120d0f92a60f9f51bb1bc1a80e34.url(options),
    method: 'get',
})

index3a82120d0f92a60f9f51bb1bc1a80e34.definition = {
    methods: ["get","head"],
    url: '/tma/manager',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
index3a82120d0f92a60f9f51bb1bc1a80e34.url = (options?: RouteQueryOptions) => {
    return index3a82120d0f92a60f9f51bb1bc1a80e34.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
index3a82120d0f92a60f9f51bb1bc1a80e34.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index3a82120d0f92a60f9f51bb1bc1a80e34.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/manager'
*/
index3a82120d0f92a60f9f51bb1bc1a80e34.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index3a82120d0f92a60f9f51bb1bc1a80e34.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
const index4a1f151c1e3cb2b6b1ba9d074c969b3e = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index4a1f151c1e3cb2b6b1ba9d074c969b3e.url(args, options),
    method: 'get',
})

index4a1f151c1e3cb2b6b1ba9d074c969b3e.definition = {
    methods: ["get","head"],
    url: '/tma/{role?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
index4a1f151c1e3cb2b6b1ba9d074c969b3e.url = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return index4a1f151c1e3cb2b6b1ba9d074c969b3e.definition.url
            .replace('{role?}', parsedArgs.role?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
index4a1f151c1e3cb2b6b1ba9d074c969b3e.get = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index4a1f151c1e3cb2b6b1ba9d074c969b3e.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:13
* @route '/tma/{role?}'
*/
index4a1f151c1e3cb2b6b1ba9d074c969b3e.head = (args?: { role?: string | number } | [role: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index4a1f151c1e3cb2b6b1ba9d074c969b3e.url(args, options),
    method: 'head',
})

/**
* Multiple routes resolve to \App\Http\Controllers\TmaController::index, so this export is a
* dictionary keyed by URI rather than a callable, with the verbs prefixed where two routes share a URI.
* Call a specific route with `index['<key>'](...)`, or import the route by name from your
* generated `routes/` directory.
*/
export const index = {
    '/': index980bb49ee7ae63891f1d891d2fbcf1c9,
    '/tma/manager': index3a82120d0f92a60f9f51bb1bc1a80e34,
    '/tma/{role?}': index4a1f151c1e3cb2b6b1ba9d074c969b3e,
}

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
* @see \App\Http\Controllers\TmaController::auditShow
* @see app/Http/Controllers/TmaController.php:107
* @route '/tma/audit/{id}'
*/
export const auditShow = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: auditShow.url(args, options),
    method: 'get',
})

auditShow.definition = {
    methods: ["get","head"],
    url: '/tma/audit/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::auditShow
* @see app/Http/Controllers/TmaController.php:107
* @route '/tma/audit/{id}'
*/
auditShow.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return auditShow.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::auditShow
* @see app/Http/Controllers/TmaController.php:107
* @route '/tma/audit/{id}'
*/
auditShow.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: auditShow.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::auditShow
* @see app/Http/Controllers/TmaController.php:107
* @route '/tma/audit/{id}'
*/
auditShow.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: auditShow.url(args, options),
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
* @see \App\Http\Controllers\TmaController::orderCreate
* @see app/Http/Controllers/TmaController.php:175
* @route '/tma/new'
*/
export const orderCreate = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderCreate.url(options),
    method: 'get',
})

orderCreate.definition = {
    methods: ["get","head"],
    url: '/tma/new',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::orderCreate
* @see app/Http/Controllers/TmaController.php:175
* @route '/tma/new'
*/
orderCreate.url = (options?: RouteQueryOptions) => {
    return orderCreate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::orderCreate
* @see app/Http/Controllers/TmaController.php:175
* @route '/tma/new'
*/
orderCreate.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderCreate.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::orderCreate
* @see app/Http/Controllers/TmaController.php:175
* @route '/tma/new'
*/
orderCreate.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderCreate.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::orderList
* @see app/Http/Controllers/TmaController.php:162
* @route '/tma/orders'
*/
export const orderList = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderList.url(options),
    method: 'get',
})

orderList.definition = {
    methods: ["get","head"],
    url: '/tma/orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::orderList
* @see app/Http/Controllers/TmaController.php:162
* @route '/tma/orders'
*/
orderList.url = (options?: RouteQueryOptions) => {
    return orderList.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::orderList
* @see app/Http/Controllers/TmaController.php:162
* @route '/tma/orders'
*/
orderList.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderList.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::orderList
* @see app/Http/Controllers/TmaController.php:162
* @route '/tma/orders'
*/
orderList.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderList.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::orderShow
* @see app/Http/Controllers/TmaController.php:212
* @route '/tma/orders/{id}'
*/
export const orderShow = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderShow.url(args, options),
    method: 'get',
})

orderShow.definition = {
    methods: ["get","head"],
    url: '/tma/orders/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::orderShow
* @see app/Http/Controllers/TmaController.php:212
* @route '/tma/orders/{id}'
*/
orderShow.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return orderShow.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::orderShow
* @see app/Http/Controllers/TmaController.php:212
* @route '/tma/orders/{id}'
*/
orderShow.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderShow.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::orderShow
* @see app/Http/Controllers/TmaController.php:212
* @route '/tma/orders/{id}'
*/
orderShow.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderShow.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::orderEdit
* @see app/Http/Controllers/TmaController.php:188
* @route '/tma/orders/{id}/edit'
*/
export const orderEdit = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderEdit.url(args, options),
    method: 'get',
})

orderEdit.definition = {
    methods: ["get","head"],
    url: '/tma/orders/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::orderEdit
* @see app/Http/Controllers/TmaController.php:188
* @route '/tma/orders/{id}/edit'
*/
orderEdit.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return orderEdit.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::orderEdit
* @see app/Http/Controllers/TmaController.php:188
* @route '/tma/orders/{id}/edit'
*/
orderEdit.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderEdit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::orderEdit
* @see app/Http/Controllers/TmaController.php:188
* @route '/tma/orders/{id}/edit'
*/
orderEdit.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderEdit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::orderChat
* @see app/Http/Controllers/TmaController.php:238
* @route '/tma/orders/{id}/chat'
*/
export const orderChat = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderChat.url(args, options),
    method: 'get',
})

orderChat.definition = {
    methods: ["get","head"],
    url: '/tma/orders/{id}/chat',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::orderChat
* @see app/Http/Controllers/TmaController.php:238
* @route '/tma/orders/{id}/chat'
*/
orderChat.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return orderChat.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::orderChat
* @see app/Http/Controllers/TmaController.php:238
* @route '/tma/orders/{id}/chat'
*/
orderChat.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderChat.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::orderChat
* @see app/Http/Controllers/TmaController.php:238
* @route '/tma/orders/{id}/chat'
*/
orderChat.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderChat.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::customers
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
export const customers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customers.url(options),
    method: 'get',
})

customers.definition = {
    methods: ["get","head"],
    url: '/tma/customers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::customers
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
customers.url = (options?: RouteQueryOptions) => {
    return customers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::customers
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
customers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::customers
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
customers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: customers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::customerShow
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
export const customerShow = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customerShow.url(args, options),
    method: 'get',
})

customerShow.definition = {
    methods: ["get","head"],
    url: '/tma/customers/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::customerShow
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
customerShow.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return customerShow.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::customerShow
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
customerShow.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customerShow.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::customerShow
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
customerShow.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: customerShow.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::leads
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
export const leads = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leads.url(options),
    method: 'get',
})

leads.definition = {
    methods: ["get","head"],
    url: '/tma/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::leads
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
leads.url = (options?: RouteQueryOptions) => {
    return leads.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::leads
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
leads.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leads.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::leads
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
leads.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: leads.url(options),
    method: 'head',
})

const TmaController = { index, personnel, audit, auditShow, settings, sites, financial, analytics, orderCreate, orderList, orderShow, orderEdit, orderChat, customers, customerShow, leads }

export default TmaController