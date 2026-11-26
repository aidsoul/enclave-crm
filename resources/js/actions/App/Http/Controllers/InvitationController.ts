import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\InvitationController::index
* @see app/Http/Controllers/InvitationController.php:22
* @route '/api/invitations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/invitations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\InvitationController::index
* @see app/Http/Controllers/InvitationController.php:22
* @route '/api/invitations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InvitationController::index
* @see app/Http/Controllers/InvitationController.php:22
* @route '/api/invitations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\InvitationController::index
* @see app/Http/Controllers/InvitationController.php:22
* @route '/api/invitations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\InvitationController::store
* @see app/Http/Controllers/InvitationController.php:46
* @route '/api/invitations'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\InvitationController::store
* @see app/Http/Controllers/InvitationController.php:46
* @route '/api/invitations'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InvitationController::store
* @see app/Http/Controllers/InvitationController.php:46
* @route '/api/invitations'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\InvitationController::destroy
* @see app/Http/Controllers/InvitationController.php:74
* @route '/api/invitations/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/invitations/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\InvitationController::destroy
* @see app/Http/Controllers/InvitationController.php:74
* @route '/api/invitations/{id}'
*/
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\InvitationController::destroy
* @see app/Http/Controllers/InvitationController.php:74
* @route '/api/invitations/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\InvitationController::accept
* @see app/Http/Controllers/InvitationController.php:92
* @route '/api/invitations/accept'
*/
export const accept = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/api/invitations/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\InvitationController::accept
* @see app/Http/Controllers/InvitationController.php:92
* @route '/api/invitations/accept'
*/
accept.url = (options?: RouteQueryOptions) => {
    return accept.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InvitationController::accept
* @see app/Http/Controllers/InvitationController.php:92
* @route '/api/invitations/accept'
*/
accept.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

const InvitationController = { index, store, destroy, accept }

export default InvitationController