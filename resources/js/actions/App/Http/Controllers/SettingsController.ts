import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SettingsController::retention
* @see app/Http/Controllers/SettingsController.php:38
* @route '/api/settings/retention'
*/
export const retention = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: retention.url(options),
    method: 'get',
})

retention.definition = {
    methods: ["get","head"],
    url: '/api/settings/retention',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SettingsController::retention
* @see app/Http/Controllers/SettingsController.php:38
* @route '/api/settings/retention'
*/
retention.url = (options?: RouteQueryOptions) => {
    return retention.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsController::retention
* @see app/Http/Controllers/SettingsController.php:38
* @route '/api/settings/retention'
*/
retention.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: retention.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SettingsController::retention
* @see app/Http/Controllers/SettingsController.php:38
* @route '/api/settings/retention'
*/
retention.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: retention.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SettingsController::updateRetention
* @see app/Http/Controllers/SettingsController.php:45
* @route '/api/settings/retention'
*/
export const updateRetention = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateRetention.url(options),
    method: 'post',
})

updateRetention.definition = {
    methods: ["post"],
    url: '/api/settings/retention',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SettingsController::updateRetention
* @see app/Http/Controllers/SettingsController.php:45
* @route '/api/settings/retention'
*/
updateRetention.url = (options?: RouteQueryOptions) => {
    return updateRetention.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsController::updateRetention
* @see app/Http/Controllers/SettingsController.php:45
* @route '/api/settings/retention'
*/
updateRetention.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateRetention.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SettingsController::index
* @see app/Http/Controllers/SettingsController.php:19
* @route '/api/settings'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SettingsController::index
* @see app/Http/Controllers/SettingsController.php:19
* @route '/api/settings'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsController::index
* @see app/Http/Controllers/SettingsController.php:19
* @route '/api/settings'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SettingsController::index
* @see app/Http/Controllers/SettingsController.php:19
* @route '/api/settings'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SettingsController::show
* @see app/Http/Controllers/SettingsController.php:57
* @route '/api/settings/{key}'
*/
export const show = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/settings/{key}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SettingsController::show
* @see app/Http/Controllers/SettingsController.php:57
* @route '/api/settings/{key}'
*/
show.url = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { key: args }
    }

    if (Array.isArray(args)) {
        args = {
            key: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        key: args.key,
    }

    return show.definition.url
            .replace('{key}', parsedArgs.key.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsController::show
* @see app/Http/Controllers/SettingsController.php:57
* @route '/api/settings/{key}'
*/
show.get = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SettingsController::show
* @see app/Http/Controllers/SettingsController.php:57
* @route '/api/settings/{key}'
*/
show.head = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SettingsController::update
* @see app/Http/Controllers/SettingsController.php:73
* @route '/api/settings/{key}'
*/
export const update = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/api/settings/{key}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SettingsController::update
* @see app/Http/Controllers/SettingsController.php:73
* @route '/api/settings/{key}'
*/
update.url = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { key: args }
    }

    if (Array.isArray(args)) {
        args = {
            key: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        key: args.key,
    }

    return update.definition.url
            .replace('{key}', parsedArgs.key.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SettingsController::update
* @see app/Http/Controllers/SettingsController.php:73
* @route '/api/settings/{key}'
*/
update.post = (args: { key: string | number } | [key: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

const SettingsController = { retention, updateRetention, index, show, update }

export default SettingsController