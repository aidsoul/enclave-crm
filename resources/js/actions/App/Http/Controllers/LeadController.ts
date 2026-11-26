import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadController::index
* @see app/Http/Controllers/LeadController.php:21
* @route '/api/leads'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeadController::index
* @see app/Http/Controllers/LeadController.php:21
* @route '/api/leads'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::index
* @see app/Http/Controllers/LeadController.php:21
* @route '/api/leads'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LeadController::index
* @see app/Http/Controllers/LeadController.php:21
* @route '/api/leads'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeadController::show
* @see app/Http/Controllers/LeadController.php:62
* @route '/api/leads/{lead}'
*/
export const show = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/leads/{lead}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeadController::show
* @see app/Http/Controllers/LeadController.php:62
* @route '/api/leads/{lead}'
*/
show.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { lead: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            lead: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        lead: typeof args.lead === 'object'
        ? args.lead.id
        : args.lead,
    }

    return show.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::show
* @see app/Http/Controllers/LeadController.php:62
* @route '/api/leads/{lead}'
*/
show.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LeadController::show
* @see app/Http/Controllers/LeadController.php:62
* @route '/api/leads/{lead}'
*/
show.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeadController::update
* @see app/Http/Controllers/LeadController.php:69
* @route '/api/leads/{lead}'
*/
export const update = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/leads/{lead}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\LeadController::update
* @see app/Http/Controllers/LeadController.php:69
* @route '/api/leads/{lead}'
*/
update.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { lead: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            lead: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        lead: typeof args.lead === 'object'
        ? args.lead.id
        : args.lead,
    }

    return update.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::update
* @see app/Http/Controllers/LeadController.php:69
* @route '/api/leads/{lead}'
*/
update.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\LeadController::destroy
* @see app/Http/Controllers/LeadController.php:114
* @route '/api/leads/{lead}'
*/
export const destroy = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/leads/{lead}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\LeadController::destroy
* @see app/Http/Controllers/LeadController.php:114
* @route '/api/leads/{lead}'
*/
destroy.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { lead: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            lead: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        lead: typeof args.lead === 'object'
        ? args.lead.id
        : args.lead,
    }

    return destroy.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::destroy
* @see app/Http/Controllers/LeadController.php:114
* @route '/api/leads/{lead}'
*/
destroy.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\LeadController::convertToOrder
* @see app/Http/Controllers/LeadController.php:91
* @route '/api/leads/{lead}/convert-to-order'
*/
export const convertToOrder = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: convertToOrder.url(args, options),
    method: 'post',
})

convertToOrder.definition = {
    methods: ["post"],
    url: '/api/leads/{lead}/convert-to-order',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadController::convertToOrder
* @see app/Http/Controllers/LeadController.php:91
* @route '/api/leads/{lead}/convert-to-order'
*/
convertToOrder.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { lead: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            lead: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        lead: typeof args.lead === 'object'
        ? args.lead.id
        : args.lead,
    }

    return convertToOrder.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::convertToOrder
* @see app/Http/Controllers/LeadController.php:91
* @route '/api/leads/{lead}/convert-to-order'
*/
convertToOrder.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: convertToOrder.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LeadController::customerLeads
* @see app/Http/Controllers/LeadController.php:98
* @route '/api/customers/{customer}/leads'
*/
export const customerLeads = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customerLeads.url(args, options),
    method: 'get',
})

customerLeads.definition = {
    methods: ["get","head"],
    url: '/api/customers/{customer}/leads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeadController::customerLeads
* @see app/Http/Controllers/LeadController.php:98
* @route '/api/customers/{customer}/leads'
*/
customerLeads.url = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customer: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { customer: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            customer: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        customer: typeof args.customer === 'object'
        ? args.customer.id
        : args.customer,
    }

    return customerLeads.definition.url
            .replace('{customer}', parsedArgs.customer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadController::customerLeads
* @see app/Http/Controllers/LeadController.php:98
* @route '/api/customers/{customer}/leads'
*/
customerLeads.get = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: customerLeads.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LeadController::customerLeads
* @see app/Http/Controllers/LeadController.php:98
* @route '/api/customers/{customer}/leads'
*/
customerLeads.head = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: customerLeads.url(args, options),
    method: 'head',
})

const LeadController = { index, show, update, destroy, convertToOrder, customerLeads }

export default LeadController