import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AuditController::index
* @see app/Http/Controllers/AuditController.php:17
* @route '/api/audit/logs'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/audit/logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AuditController::index
* @see app/Http/Controllers/AuditController.php:17
* @route '/api/audit/logs'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuditController::index
* @see app/Http/Controllers/AuditController.php:17
* @route '/api/audit/logs'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AuditController::index
* @see app/Http/Controllers/AuditController.php:17
* @route '/api/audit/logs'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AuditController::show
* @see app/Http/Controllers/AuditController.php:39
* @route '/api/audit/logs/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/audit/logs/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AuditController::show
* @see app/Http/Controllers/AuditController.php:39
* @route '/api/audit/logs/{id}'
*/
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuditController::show
* @see app/Http/Controllers/AuditController.php:39
* @route '/api/audit/logs/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AuditController::show
* @see app/Http/Controllers/AuditController.php:39
* @route '/api/audit/logs/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AuditController::orderLogs
* @see app/Http/Controllers/AuditController.php:32
* @route '/api/audit/orders/{orderId}/logs'
*/
export const orderLogs = (args: { orderId: string | number } | [orderId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderLogs.url(args, options),
    method: 'get',
})

orderLogs.definition = {
    methods: ["get","head"],
    url: '/api/audit/orders/{orderId}/logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AuditController::orderLogs
* @see app/Http/Controllers/AuditController.php:32
* @route '/api/audit/orders/{orderId}/logs'
*/
orderLogs.url = (args: { orderId: string | number } | [orderId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { orderId: args }
    }

    if (Array.isArray(args)) {
        args = {
            orderId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        orderId: args.orderId,
    }

    return orderLogs.definition.url
            .replace('{orderId}', parsedArgs.orderId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AuditController::orderLogs
* @see app/Http/Controllers/AuditController.php:32
* @route '/api/audit/orders/{orderId}/logs'
*/
orderLogs.get = (args: { orderId: string | number } | [orderId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orderLogs.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\AuditController::orderLogs
* @see app/Http/Controllers/AuditController.php:32
* @route '/api/audit/orders/{orderId}/logs'
*/
orderLogs.head = (args: { orderId: string | number } | [orderId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orderLogs.url(args, options),
    method: 'head',
})

const AuditController = { index, show, orderLogs }

export default AuditController