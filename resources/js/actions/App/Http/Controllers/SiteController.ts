import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SiteController::index
* @see app/Http/Controllers/SiteController.php:14
* @route '/api/sites'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/sites',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SiteController::index
* @see app/Http/Controllers/SiteController.php:14
* @route '/api/sites'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::index
* @see app/Http/Controllers/SiteController.php:14
* @route '/api/sites'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::index
* @see app/Http/Controllers/SiteController.php:14
* @route '/api/sites'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:63
* @route '/api/sites'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/sites',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:63
* @route '/api/sites'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:63
* @route '/api/sites'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:55
* @route '/api/sites/{site}'
*/
export const show = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/sites/{site}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:55
* @route '/api/sites/{site}'
*/
show.url = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { site: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: typeof args.site === 'object'
        ? args.site.id
        : args.site,
    }

    return show.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:55
* @route '/api/sites/{site}'
*/
show.get = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:55
* @route '/api/sites/{site}'
*/
show.head = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:74
* @route '/api/sites/{site}'
*/
export const update = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/sites/{site}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:74
* @route '/api/sites/{site}'
*/
update.url = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { site: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: typeof args.site === 'object'
        ? args.site.id
        : args.site,
    }

    return update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:74
* @route '/api/sites/{site}'
*/
update.patch = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\SiteController::rotateToken
* @see app/Http/Controllers/SiteController.php:85
* @route '/api/sites/{site}/rotate-token'
*/
export const rotateToken = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rotateToken.url(args, options),
    method: 'post',
})

rotateToken.definition = {
    methods: ["post"],
    url: '/api/sites/{site}/rotate-token',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteController::rotateToken
* @see app/Http/Controllers/SiteController.php:85
* @route '/api/sites/{site}/rotate-token'
*/
rotateToken.url = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { site: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: typeof args.site === 'object'
        ? args.site.id
        : args.site,
    }

    return rotateToken.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::rotateToken
* @see app/Http/Controllers/SiteController.php:85
* @route '/api/sites/{site}/rotate-token'
*/
rotateToken.post = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rotateToken.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::revokeToken
* @see app/Http/Controllers/SiteController.php:95
* @route '/api/sites/{site}/revoke-token'
*/
export const revokeToken = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: revokeToken.url(args, options),
    method: 'post',
})

revokeToken.definition = {
    methods: ["post"],
    url: '/api/sites/{site}/revoke-token',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteController::revokeToken
* @see app/Http/Controllers/SiteController.php:95
* @route '/api/sites/{site}/revoke-token'
*/
revokeToken.url = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { site: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: typeof args.site === 'object'
        ? args.site.id
        : args.site,
    }

    return revokeToken.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::revokeToken
* @see app/Http/Controllers/SiteController.php:95
* @route '/api/sites/{site}/revoke-token'
*/
revokeToken.post = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: revokeToken.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:104
* @route '/api/sites/{site}'
*/
export const destroy = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/sites/{site}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:104
* @route '/api/sites/{site}'
*/
destroy.url = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { site: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: typeof args.site === 'object'
        ? args.site.id
        : args.site,
    }

    return destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:104
* @route '/api/sites/{site}'
*/
destroy.delete = (args: { site: number | { id: number } } | [site: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const SiteController = { index, store, show, update, rotateToken, revokeToken, destroy }

export default SiteController