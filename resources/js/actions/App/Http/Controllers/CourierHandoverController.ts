import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CourierHandoverController::orders
* @see app/Http/Controllers/CourierHandoverController.php:18
* @route '/api/courier/handover/orders'
*/
export const orders = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orders.url(options),
    method: 'get',
})

orders.definition = {
    methods: ["get","head"],
    url: '/api/courier/handover/orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::orders
* @see app/Http/Controllers/CourierHandoverController.php:18
* @route '/api/courier/handover/orders'
*/
orders.url = (options?: RouteQueryOptions) => {
    return orders.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::orders
* @see app/Http/Controllers/CourierHandoverController.php:18
* @route '/api/courier/handover/orders'
*/
orders.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orders.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::orders
* @see app/Http/Controllers/CourierHandoverController.php:18
* @route '/api/courier/handover/orders'
*/
orders.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orders.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::active
* @see app/Http/Controllers/CourierHandoverController.php:65
* @route '/api/courier/handover/active'
*/
export const active = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: active.url(options),
    method: 'get',
})

active.definition = {
    methods: ["get","head"],
    url: '/api/courier/handover/active',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::active
* @see app/Http/Controllers/CourierHandoverController.php:65
* @route '/api/courier/handover/active'
*/
active.url = (options?: RouteQueryOptions) => {
    return active.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::active
* @see app/Http/Controllers/CourierHandoverController.php:65
* @route '/api/courier/handover/active'
*/
active.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: active.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::active
* @see app/Http/Controllers/CourierHandoverController.php:65
* @route '/api/courier/handover/active'
*/
active.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: active.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::initiate
* @see app/Http/Controllers/CourierHandoverController.php:102
* @route '/api/courier/handover/initiate'
*/
export const initiate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

initiate.definition = {
    methods: ["post"],
    url: '/api/courier/handover/initiate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::initiate
* @see app/Http/Controllers/CourierHandoverController.php:102
* @route '/api/courier/handover/initiate'
*/
initiate.url = (options?: RouteQueryOptions) => {
    return initiate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::initiate
* @see app/Http/Controllers/CourierHandoverController.php:102
* @route '/api/courier/handover/initiate'
*/
initiate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: initiate.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::cancel
* @see app/Http/Controllers/CourierHandoverController.php:164
* @route '/api/courier/handover/cancel'
*/
export const cancel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/api/courier/handover/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::cancel
* @see app/Http/Controllers/CourierHandoverController.php:164
* @route '/api/courier/handover/cancel'
*/
cancel.url = (options?: RouteQueryOptions) => {
    return cancel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::cancel
* @see app/Http/Controllers/CourierHandoverController.php:164
* @route '/api/courier/handover/cancel'
*/
cancel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::preview
* @see app/Http/Controllers/CourierHandoverController.php:182
* @route '/api/courier/handover/preview/{code}'
*/
export const preview = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/api/courier/handover/preview/{code}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::preview
* @see app/Http/Controllers/CourierHandoverController.php:182
* @route '/api/courier/handover/preview/{code}'
*/
preview.url = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { code: args }
    }

    if (Array.isArray(args)) {
        args = {
            code: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        code: args.code,
    }

    return preview.definition.url
            .replace('{code}', parsedArgs.code.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::preview
* @see app/Http/Controllers/CourierHandoverController.php:182
* @route '/api/courier/handover/preview/{code}'
*/
preview.get = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::preview
* @see app/Http/Controllers/CourierHandoverController.php:182
* @route '/api/courier/handover/preview/{code}'
*/
preview.head = (args: { code: string | number } | [code: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CourierHandoverController::accept
* @see app/Http/Controllers/CourierHandoverController.php:198
* @route '/api/courier/handover/accept'
*/
export const accept = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/api/courier/handover/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CourierHandoverController::accept
* @see app/Http/Controllers/CourierHandoverController.php:198
* @route '/api/courier/handover/accept'
*/
accept.url = (options?: RouteQueryOptions) => {
    return accept.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CourierHandoverController::accept
* @see app/Http/Controllers/CourierHandoverController.php:198
* @route '/api/courier/handover/accept'
*/
accept.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

const CourierHandoverController = { orders, active, initiate, cancel, preview, accept }

export default CourierHandoverController