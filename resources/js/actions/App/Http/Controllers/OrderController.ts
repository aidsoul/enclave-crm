import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:48
* @route '/api/orders'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:48
* @route '/api/orders'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:48
* @route '/api/orders'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::index
* @see app/Http/Controllers/OrderController.php:48
* @route '/api/orders'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:92
* @route '/api/orders'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/orders',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:92
* @route '/api/orders'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::store
* @see app/Http/Controllers/OrderController.php:92
* @route '/api/orders'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::bulkAssign
* @see app/Http/Controllers/OrderController.php:565
* @route '/api/orders/bulk-assign'
*/
export const bulkAssign = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAssign.url(options),
    method: 'post',
})

bulkAssign.definition = {
    methods: ["post"],
    url: '/api/orders/bulk-assign',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::bulkAssign
* @see app/Http/Controllers/OrderController.php:565
* @route '/api/orders/bulk-assign'
*/
bulkAssign.url = (options?: RouteQueryOptions) => {
    return bulkAssign.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::bulkAssign
* @see app/Http/Controllers/OrderController.php:565
* @route '/api/orders/bulk-assign'
*/
bulkAssign.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAssign.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::bulkAccept
* @see app/Http/Controllers/OrderController.php:577
* @route '/api/orders/bulk-accept'
*/
export const bulkAccept = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAccept.url(options),
    method: 'post',
})

bulkAccept.definition = {
    methods: ["post"],
    url: '/api/orders/bulk-accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::bulkAccept
* @see app/Http/Controllers/OrderController.php:577
* @route '/api/orders/bulk-accept'
*/
bulkAccept.url = (options?: RouteQueryOptions) => {
    return bulkAccept.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::bulkAccept
* @see app/Http/Controllers/OrderController.php:577
* @route '/api/orders/bulk-accept'
*/
bulkAccept.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAccept.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::bulkAssignManager
* @see app/Http/Controllers/OrderController.php:585
* @route '/api/orders/bulk-assign-manager'
*/
export const bulkAssignManager = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAssignManager.url(options),
    method: 'post',
})

bulkAssignManager.definition = {
    methods: ["post"],
    url: '/api/orders/bulk-assign-manager',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::bulkAssignManager
* @see app/Http/Controllers/OrderController.php:585
* @route '/api/orders/bulk-assign-manager'
*/
bulkAssignManager.url = (options?: RouteQueryOptions) => {
    return bulkAssignManager.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::bulkAssignManager
* @see app/Http/Controllers/OrderController.php:585
* @route '/api/orders/bulk-assign-manager'
*/
bulkAssignManager.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAssignManager.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::bulkAtWarehouse
* @see app/Http/Controllers/OrderController.php:597
* @route '/api/orders/bulk-at-warehouse'
*/
export const bulkAtWarehouse = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAtWarehouse.url(options),
    method: 'post',
})

bulkAtWarehouse.definition = {
    methods: ["post"],
    url: '/api/orders/bulk-at-warehouse',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::bulkAtWarehouse
* @see app/Http/Controllers/OrderController.php:597
* @route '/api/orders/bulk-at-warehouse'
*/
bulkAtWarehouse.url = (options?: RouteQueryOptions) => {
    return bulkAtWarehouse.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::bulkAtWarehouse
* @see app/Http/Controllers/OrderController.php:597
* @route '/api/orders/bulk-at-warehouse'
*/
bulkAtWarehouse.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAtWarehouse.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::bulkCancel
* @see app/Http/Controllers/OrderController.php:557
* @route '/api/orders/bulk-cancel'
*/
export const bulkCancel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkCancel.url(options),
    method: 'post',
})

bulkCancel.definition = {
    methods: ["post"],
    url: '/api/orders/bulk-cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::bulkCancel
* @see app/Http/Controllers/OrderController.php:557
* @route '/api/orders/bulk-cancel'
*/
bulkCancel.url = (options?: RouteQueryOptions) => {
    return bulkCancel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::bulkCancel
* @see app/Http/Controllers/OrderController.php:557
* @route '/api/orders/bulk-cancel'
*/
bulkCancel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkCancel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:105
* @route '/api/orders/{order}'
*/
export const update = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:105
* @route '/api/orders/{order}'
*/
update.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::update
* @see app/Http/Controllers/OrderController.php:105
* @route '/api/orders/{order}'
*/
update.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:114
* @route '/api/orders/{order}/assign'
*/
export const assign = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

assign.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/assign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:114
* @route '/api/orders/{order}/assign'
*/
assign.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return assign.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::assign
* @see app/Http/Controllers/OrderController.php:114
* @route '/api/orders/{order}/assign'
*/
assign.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::accept
* @see app/Http/Controllers/OrderController.php:146
* @route '/api/orders/{order}/accept'
*/
export const accept = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

accept.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/accept',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::accept
* @see app/Http/Controllers/OrderController.php:146
* @route '/api/orders/{order}/accept'
*/
accept.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return accept.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::accept
* @see app/Http/Controllers/OrderController.php:146
* @route '/api/orders/{order}/accept'
*/
accept.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::atWarehouse
* @see app/Http/Controllers/OrderController.php:157
* @route '/api/orders/{order}/at-warehouse'
*/
export const atWarehouse = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: atWarehouse.url(args, options),
    method: 'patch',
})

atWarehouse.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/at-warehouse',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::atWarehouse
* @see app/Http/Controllers/OrderController.php:157
* @route '/api/orders/{order}/at-warehouse'
*/
atWarehouse.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return atWarehouse.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::atWarehouse
* @see app/Http/Controllers/OrderController.php:157
* @route '/api/orders/{order}/at-warehouse'
*/
atWarehouse.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: atWarehouse.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:164
* @route '/api/orders/{order}/complete'
*/
export const complete = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(args, options),
    method: 'patch',
})

complete.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/complete',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:164
* @route '/api/orders/{order}/complete'
*/
complete.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return complete.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::complete
* @see app/Http/Controllers/OrderController.php:164
* @route '/api/orders/{order}/complete'
*/
complete.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::uploadProof
* @see app/Http/Controllers/OrderController.php:301
* @route '/api/orders/{order}/delivery-proof'
*/
export const uploadProof = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadProof.url(args, options),
    method: 'post',
})

uploadProof.definition = {
    methods: ["post"],
    url: '/api/orders/{order}/delivery-proof',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::uploadProof
* @see app/Http/Controllers/OrderController.php:301
* @route '/api/orders/{order}/delivery-proof'
*/
uploadProof.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return uploadProof.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::uploadProof
* @see app/Http/Controllers/OrderController.php:301
* @route '/api/orders/{order}/delivery-proof'
*/
uploadProof.post = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadProof.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::deleteProof
* @see app/Http/Controllers/OrderController.php:437
* @route '/api/orders/{order}/delivery-proof'
*/
export const deleteProof = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteProof.url(args, options),
    method: 'delete',
})

deleteProof.definition = {
    methods: ["delete"],
    url: '/api/orders/{order}/delivery-proof',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderController::deleteProof
* @see app/Http/Controllers/OrderController.php:437
* @route '/api/orders/{order}/delivery-proof'
*/
deleteProof.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return deleteProof.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::deleteProof
* @see app/Http/Controllers/OrderController.php:437
* @route '/api/orders/{order}/delivery-proof'
*/
deleteProof.delete = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteProof.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OrderController::uploadSignature
* @see app/Http/Controllers/OrderController.php:464
* @route '/api/orders/{order}/delivery-signature'
*/
export const uploadSignature = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSignature.url(args, options),
    method: 'post',
})

uploadSignature.definition = {
    methods: ["post"],
    url: '/api/orders/{order}/delivery-signature',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OrderController::uploadSignature
* @see app/Http/Controllers/OrderController.php:464
* @route '/api/orders/{order}/delivery-signature'
*/
uploadSignature.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return uploadSignature.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::uploadSignature
* @see app/Http/Controllers/OrderController.php:464
* @route '/api/orders/{order}/delivery-signature'
*/
uploadSignature.post = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSignature.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OrderController::deleteSignature
* @see app/Http/Controllers/OrderController.php:522
* @route '/api/orders/{order}/delivery-signature'
*/
export const deleteSignature = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteSignature.url(args, options),
    method: 'delete',
})

deleteSignature.definition = {
    methods: ["delete"],
    url: '/api/orders/{order}/delivery-signature',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderController::deleteSignature
* @see app/Http/Controllers/OrderController.php:522
* @route '/api/orders/{order}/delivery-signature'
*/
deleteSignature.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return deleteSignature.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::deleteSignature
* @see app/Http/Controllers/OrderController.php:522
* @route '/api/orders/{order}/delivery-signature'
*/
deleteSignature.delete = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteSignature.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:605
* @route '/api/orders/{order}'
*/
export const destroy = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/orders/{order}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:605
* @route '/api/orders/{order}'
*/
destroy.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::destroy
* @see app/Http/Controllers/OrderController.php:605
* @route '/api/orders/{order}'
*/
destroy.delete = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OrderController::cancel
* @see app/Http/Controllers/OrderController.php:550
* @route '/api/orders/{order}/cancel'
*/
export const cancel = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

cancel.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/cancel',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::cancel
* @see app/Http/Controllers/OrderController.php:550
* @route '/api/orders/{order}/cancel'
*/
cancel.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::cancel
* @see app/Http/Controllers/OrderController.php:550
* @route '/api/orders/{order}/cancel'
*/
cancel.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:87
* @route '/api/orders/{order}'
*/
export const show = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/orders/{order}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:87
* @route '/api/orders/{order}'
*/
show.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:87
* @route '/api/orders/{order}'
*/
show.get = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\OrderController::show
* @see app/Http/Controllers/OrderController.php:87
* @route '/api/orders/{order}'
*/
show.head = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OrderController::status
* @see app/Http/Controllers/OrderController.php:133
* @route '/api/orders/{order}/status'
*/
export const status = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

status.definition = {
    methods: ["patch"],
    url: '/api/orders/{order}/status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OrderController::status
* @see app/Http/Controllers/OrderController.php:133
* @route '/api/orders/{order}/status'
*/
status.url = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return status.definition.url
            .replace('{order}', parsedArgs.order.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OrderController::status
* @see app/Http/Controllers/OrderController.php:133
* @route '/api/orders/{order}/status'
*/
status.patch = (args: { order: number | { id: number } } | [order: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: status.url(args, options),
    method: 'patch',
})

const OrderController = { index, store, bulkAssign, bulkAccept, bulkAssignManager, bulkAtWarehouse, bulkCancel, update, assign, accept, atWarehouse, complete, uploadProof, deleteProof, uploadSignature, deleteSignature, destroy, cancel, show, status }

export default OrderController