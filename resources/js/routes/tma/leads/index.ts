import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/tma/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TmaController::index
* @see app/Http/Controllers/TmaController.php:290
* @route '/tma/leads'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const leads = {
    index: Object.assign(index, index),
}

export default leads