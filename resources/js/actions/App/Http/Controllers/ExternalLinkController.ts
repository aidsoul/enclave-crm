import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ExternalLinkController::viber
* @see app/Http/Controllers/ExternalLinkController.php:9
* @route '/open/viber'
*/
export const viber = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: viber.url(options),
    method: 'get',
})

viber.definition = {
    methods: ["get","head"],
    url: '/open/viber',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ExternalLinkController::viber
* @see app/Http/Controllers/ExternalLinkController.php:9
* @route '/open/viber'
*/
viber.url = (options?: RouteQueryOptions) => {
    return viber.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ExternalLinkController::viber
* @see app/Http/Controllers/ExternalLinkController.php:9
* @route '/open/viber'
*/
viber.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: viber.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ExternalLinkController::viber
* @see app/Http/Controllers/ExternalLinkController.php:9
* @route '/open/viber'
*/
viber.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: viber.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ExternalLinkController::email
* @see app/Http/Controllers/ExternalLinkController.php:26
* @route '/open/email'
*/
export const email = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: email.url(options),
    method: 'get',
})

email.definition = {
    methods: ["get","head"],
    url: '/open/email',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ExternalLinkController::email
* @see app/Http/Controllers/ExternalLinkController.php:26
* @route '/open/email'
*/
email.url = (options?: RouteQueryOptions) => {
    return email.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ExternalLinkController::email
* @see app/Http/Controllers/ExternalLinkController.php:26
* @route '/open/email'
*/
email.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: email.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ExternalLinkController::email
* @see app/Http/Controllers/ExternalLinkController.php:26
* @route '/open/email'
*/
email.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: email.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ExternalLinkController::telegram
* @see app/Http/Controllers/ExternalLinkController.php:43
* @route '/open/telegram'
*/
export const telegram = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: telegram.url(options),
    method: 'get',
})

telegram.definition = {
    methods: ["get","head"],
    url: '/open/telegram',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ExternalLinkController::telegram
* @see app/Http/Controllers/ExternalLinkController.php:43
* @route '/open/telegram'
*/
telegram.url = (options?: RouteQueryOptions) => {
    return telegram.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ExternalLinkController::telegram
* @see app/Http/Controllers/ExternalLinkController.php:43
* @route '/open/telegram'
*/
telegram.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: telegram.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ExternalLinkController::telegram
* @see app/Http/Controllers/ExternalLinkController.php:43
* @route '/open/telegram'
*/
telegram.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: telegram.url(options),
    method: 'head',
})

const ExternalLinkController = { viber, email, telegram }

export default ExternalLinkController