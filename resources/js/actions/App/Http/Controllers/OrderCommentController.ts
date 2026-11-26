import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OrderCommentController::index
* @see app/Http/Controllers/OrderCommentController.php:15
* @route '/api/orders/{order}/comments'
*/
export const index = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/orders/{order}/comments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderCommentController::index
* @see app/Http/Controllers/OrderCommentController.php:15
* @route '/api/orders/{order}/comments'
*/
index.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { order: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: typeof args.order === 'object'
        ? args.order.id
        : args.order,
    }

    return index.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderCommentController::index
* @see app/Http/Controllers/OrderCommentController.php:15
* @route '/api/orders/{order}/comments'
*/
index.get = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderCommentController::index
* @see app/Http/Controllers/OrderCommentController.php:15
* @route '/api/orders/{order}/comments'
*/
index.head = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderCommentController::store
* @see app/Http/Controllers/OrderCommentController.php:65
* @route '/api/orders/{order}/comments'
*/
export const store = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/orders/{order}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderCommentController::store
* @see app/Http/Controllers/OrderCommentController.php:65
* @route '/api/orders/{order}/comments'
*/
store.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { order: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { order: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            order: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: typeof args.order === 'object'
        ? args.order.id
        : args.order,
    }

    return store.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderCommentController::store
* @see app/Http/Controllers/OrderCommentController.php:65
* @route '/api/orders/{order}/comments'
*/
store.post = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderCommentController::destroy
* @see app/Http/Controllers/OrderCommentController.php:88
* @route '/api/orders/{order}/comments/{comment}'
*/
export const destroy = (args: { order: number | { id: number }, comment: string | number } | [order: number | { id: number }, comment: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/orders/{order}/comments/{comment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderCommentController::destroy
* @see app/Http/Controllers/OrderCommentController.php:88
* @route '/api/orders/{order}/comments/{comment}'
*/
destroy.url = (args: { order: number | { id: number }, comment: string | number } | [order: number | { id: number }, comment: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            order: args[0],
            comment: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: typeof args.order === 'object'
        ? args.order.id
        : args.order,
        comment: args.comment,
    }

    return destroy.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace('{comment}', parsedArgs.comment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderCommentController::destroy
* @see app/Http/Controllers/OrderCommentController.php:88
* @route '/api/orders/{order}/comments/{comment}'
*/
destroy.delete = (args: { order: number | { id: number }, comment: string | number } | [order: number | { id: number }, comment: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const OrderCommentController = { index, store, destroy }

export default OrderCommentController