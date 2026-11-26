import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OrderMessageController::activeChatOrders
* @see app/Http/Controllers/OrderMessageController.php:67
* @route '/api/orders/chat-active-orders'
*/
export const activeChatOrders = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activeChatOrders.url(options),
    method: 'get',
})

activeChatOrders.definition = {
    methods: ["get","head"],
    url: '/api/orders/chat-active-orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderMessageController::activeChatOrders
* @see app/Http/Controllers/OrderMessageController.php:67
* @route '/api/orders/chat-active-orders'
*/
activeChatOrders.url = (options?: RouteQueryOptions) => {
    return activeChatOrders.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderMessageController::activeChatOrders
* @see app/Http/Controllers/OrderMessageController.php:67
* @route '/api/orders/chat-active-orders'
*/
activeChatOrders.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activeChatOrders.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderMessageController::activeChatOrders
* @see app/Http/Controllers/OrderMessageController.php:67
* @route '/api/orders/chat-active-orders'
*/
activeChatOrders.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: activeChatOrders.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderMessageController::searchChatOrders
* @see app/Http/Controllers/OrderMessageController.php:124
* @route '/api/orders/chat-search-orders'
*/
export const searchChatOrders = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchChatOrders.url(options),
    method: 'get',
})

searchChatOrders.definition = {
    methods: ["get","head"],
    url: '/api/orders/chat-search-orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderMessageController::searchChatOrders
* @see app/Http/Controllers/OrderMessageController.php:124
* @route '/api/orders/chat-search-orders'
*/
searchChatOrders.url = (options?: RouteQueryOptions) => {
    return searchChatOrders.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderMessageController::searchChatOrders
* @see app/Http/Controllers/OrderMessageController.php:124
* @route '/api/orders/chat-search-orders'
*/
searchChatOrders.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: searchChatOrders.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderMessageController::searchChatOrders
* @see app/Http/Controllers/OrderMessageController.php:124
* @route '/api/orders/chat-search-orders'
*/
searchChatOrders.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: searchChatOrders.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderMessageController::unreadSummary
* @see app/Http/Controllers/OrderMessageController.php:26
* @route '/api/orders/chat-unread-summary'
*/
export const unreadSummary = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadSummary.url(options),
    method: 'get',
})

unreadSummary.definition = {
    methods: ["get","head"],
    url: '/api/orders/chat-unread-summary',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderMessageController::unreadSummary
* @see app/Http/Controllers/OrderMessageController.php:26
* @route '/api/orders/chat-unread-summary'
*/
unreadSummary.url = (options?: RouteQueryOptions) => {
    return unreadSummary.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderMessageController::unreadSummary
* @see app/Http/Controllers/OrderMessageController.php:26
* @route '/api/orders/chat-unread-summary'
*/
unreadSummary.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadSummary.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderMessageController::unreadSummary
* @see app/Http/Controllers/OrderMessageController.php:26
* @route '/api/orders/chat-unread-summary'
*/
unreadSummary.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: unreadSummary.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderMessageController::index
* @see app/Http/Controllers/OrderMessageController.php:193
* @route '/api/orders/{order}/messages'
*/
export const index = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/orders/{order}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderMessageController::index
* @see app/Http/Controllers/OrderMessageController.php:193
* @route '/api/orders/{order}/messages'
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
* @see \App\Http\Controllers\OrderMessageController::index
* @see app/Http/Controllers/OrderMessageController.php:193
* @route '/api/orders/{order}/messages'
*/
index.get = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderMessageController::index
* @see app/Http/Controllers/OrderMessageController.php:193
* @route '/api/orders/{order}/messages'
*/
index.head = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderMessageController::store
* @see app/Http/Controllers/OrderMessageController.php:245
* @route '/api/orders/{order}/messages'
*/
export const store = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/orders/{order}/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderMessageController::store
* @see app/Http/Controllers/OrderMessageController.php:245
* @route '/api/orders/{order}/messages'
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
* @see \App\Http\Controllers\OrderMessageController::store
* @see app/Http/Controllers/OrderMessageController.php:245
* @route '/api/orders/{order}/messages'
*/
store.post = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderMessageController::update
* @see app/Http/Controllers/OrderMessageController.php:308
* @route '/api/orders/{order}/messages/{message}'
*/
export const update = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/api/orders/{order}/messages/{message}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\OrderMessageController::update
* @see app/Http/Controllers/OrderMessageController.php:308
* @route '/api/orders/{order}/messages/{message}'
*/
update.url = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            order: args[0],
            message: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: typeof args.order === 'object'
        ? args.order.id
        : args.order,
        message: typeof args.message === 'object'
        ? args.message.id
        : args.message,
    }

    return update.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace('{message}', parsedArgs.message.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderMessageController::update
* @see app/Http/Controllers/OrderMessageController.php:308
* @route '/api/orders/{order}/messages/{message}'
*/
update.put = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OrderMessageController::update
* @see app/Http/Controllers/OrderMessageController.php:308
* @route '/api/orders/{order}/messages/{message}'
*/
update.patch = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderMessageController::destroy
* @see app/Http/Controllers/OrderMessageController.php:377
* @route '/api/orders/{order}/messages/{message}'
*/
export const destroy = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/orders/{order}/messages/{message}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderMessageController::destroy
* @see app/Http/Controllers/OrderMessageController.php:377
* @route '/api/orders/{order}/messages/{message}'
*/
destroy.url = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            order: args[0],
            message: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        order: typeof args.order === 'object'
        ? args.order.id
        : args.order,
        message: typeof args.message === 'object'
        ? args.message.id
        : args.message,
    }

    return destroy.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace('{message}', parsedArgs.message.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderMessageController::destroy
* @see app/Http/Controllers/OrderMessageController.php:377
* @route '/api/orders/{order}/messages/{message}'
*/
destroy.delete = (args: { order: number | { id: number }, message: number | { id: number } } | [order: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const OrderMessageController = { activeChatOrders, searchChatOrders, unreadSummary, index, store, update, destroy }

export default OrderMessageController