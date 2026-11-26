import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/tma/customers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:263
* @route '/tma/customers'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TmaController::show
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/tma/customers/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::show
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
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
* @see \App\Http\Controllers\TmaController::show
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::show
* @see app/Http/Controllers/TmaController.php:276
* @route '/tma/customers/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const customers = {
    index: Object.assign(index, index),
    show: Object.assign(show, show),
}

export default customers