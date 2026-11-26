import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::verify
* @see app/Http/Controllers/Auth/CloudPasswordController.php:23
* @route '/api/auth/cloud-password/verify'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

verify.definition = {
    methods: ["post"],
    url: '/api/auth/cloud-password/verify',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::verify
* @see app/Http/Controllers/Auth/CloudPasswordController.php:23
* @route '/api/auth/cloud-password/verify'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::verify
* @see app/Http/Controllers/Auth/CloudPasswordController.php:23
* @route '/api/auth/cloud-password/verify'
*/
verify.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: verify.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::setup
* @see app/Http/Controllers/Auth/CloudPasswordController.php:71
* @route '/api/auth/cloud-password/setup'
*/
export const setup = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setup.url(options),
    method: 'post',
})

setup.definition = {
    methods: ["post"],
    url: '/api/auth/cloud-password/setup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::setup
* @see app/Http/Controllers/Auth/CloudPasswordController.php:71
* @route '/api/auth/cloud-password/setup'
*/
setup.url = (options?: RouteQueryOptions) => {
    return setup.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::setup
* @see app/Http/Controllers/Auth/CloudPasswordController.php:71
* @route '/api/auth/cloud-password/setup'
*/
setup.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setup.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::change
* @see app/Http/Controllers/Auth/CloudPasswordController.php:129
* @route '/api/auth/cloud-password/change'
*/
export const change = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(options),
    method: 'post',
})

change.definition = {
    methods: ["post"],
    url: '/api/auth/cloud-password/change',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::change
* @see app/Http/Controllers/Auth/CloudPasswordController.php:129
* @route '/api/auth/cloud-password/change'
*/
change.url = (options?: RouteQueryOptions) => {
    return change.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::change
* @see app/Http/Controllers/Auth/CloudPasswordController.php:129
* @route '/api/auth/cloud-password/change'
*/
change.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: change.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::reset
* @see app/Http/Controllers/Auth/CloudPasswordController.php:173
* @route '/api/admin/users/{user}/reset-cloud-password'
*/
export const reset = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/api/admin/users/{user}/reset-cloud-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::reset
* @see app/Http/Controllers/Auth/CloudPasswordController.php:173
* @route '/api/admin/users/{user}/reset-cloud-password'
*/
reset.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { user: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            user: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        user: typeof args.user === 'object'
        ? args.user.id
        : args.user,
    }

    return reset.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\CloudPasswordController::reset
* @see app/Http/Controllers/Auth/CloudPasswordController.php:173
* @route '/api/admin/users/{user}/reset-cloud-password'
*/
reset.post = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

const CloudPasswordController = { verify, setup, change, reset }

export default CloudPasswordController