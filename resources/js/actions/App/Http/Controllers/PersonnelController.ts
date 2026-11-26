import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PersonnelController::couriers
* @see app/Http/Controllers/PersonnelController.php:87
* @route '/api/personnel/couriers'
*/
export const couriers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: couriers.url(options),
    method: 'get',
})

couriers.definition = {
    methods: ["get","head"],
    url: '/api/personnel/couriers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PersonnelController::couriers
* @see app/Http/Controllers/PersonnelController.php:87
* @route '/api/personnel/couriers'
*/
couriers.url = (options?: RouteQueryOptions) => {
    return couriers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::couriers
* @see app/Http/Controllers/PersonnelController.php:87
* @route '/api/personnel/couriers'
*/
couriers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: couriers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PersonnelController::couriers
* @see app/Http/Controllers/PersonnelController.php:87
* @route '/api/personnel/couriers'
*/
couriers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: couriers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PersonnelController::managers
* @see app/Http/Controllers/PersonnelController.php:92
* @route '/api/personnel/managers'
*/
export const managers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: managers.url(options),
    method: 'get',
})

managers.definition = {
    methods: ["get","head"],
    url: '/api/personnel/managers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PersonnelController::managers
* @see app/Http/Controllers/PersonnelController.php:92
* @route '/api/personnel/managers'
*/
managers.url = (options?: RouteQueryOptions) => {
    return managers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::managers
* @see app/Http/Controllers/PersonnelController.php:92
* @route '/api/personnel/managers'
*/
managers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: managers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PersonnelController::managers
* @see app/Http/Controllers/PersonnelController.php:92
* @route '/api/personnel/managers'
*/
managers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: managers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PersonnelController::search
* @see app/Http/Controllers/PersonnelController.php:97
* @route '/api/personnel/search'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/api/personnel/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PersonnelController::search
* @see app/Http/Controllers/PersonnelController.php:97
* @route '/api/personnel/search'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::search
* @see app/Http/Controllers/PersonnelController.php:97
* @route '/api/personnel/search'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PersonnelController::search
* @see app/Http/Controllers/PersonnelController.php:97
* @route '/api/personnel/search'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PersonnelController::index
* @see app/Http/Controllers/PersonnelController.php:23
* @route '/api/personnel'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/personnel',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PersonnelController::index
* @see app/Http/Controllers/PersonnelController.php:23
* @route '/api/personnel'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::index
* @see app/Http/Controllers/PersonnelController.php:23
* @route '/api/personnel'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PersonnelController::index
* @see app/Http/Controllers/PersonnelController.php:23
* @route '/api/personnel'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PersonnelController::store
* @see app/Http/Controllers/PersonnelController.php:64
* @route '/api/personnel'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/personnel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PersonnelController::store
* @see app/Http/Controllers/PersonnelController.php:64
* @route '/api/personnel'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::store
* @see app/Http/Controllers/PersonnelController.php:64
* @route '/api/personnel'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PersonnelController::update
* @see app/Http/Controllers/PersonnelController.php:75
* @route '/api/personnel/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/personnel/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\PersonnelController::update
* @see app/Http/Controllers/PersonnelController.php:75
* @route '/api/personnel/{id}'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PersonnelController::update
* @see app/Http/Controllers/PersonnelController.php:75
* @route '/api/personnel/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\PersonnelController::destroy
* @see app/Http/Controllers/PersonnelController.php:112
* @route '/api/personnel/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/personnel/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PersonnelController::destroy
* @see app/Http/Controllers/PersonnelController.php:112
* @route '/api/personnel/{id}'
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
* @see \App\Http\Controllers\PersonnelController::destroy
* @see app/Http/Controllers/PersonnelController.php:112
* @route '/api/personnel/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const PersonnelController = { couriers, managers, search, index, store, update, destroy }

export default PersonnelController