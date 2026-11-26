import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DashboardController::courierShiftSummary
* @see app/Http/Controllers/DashboardController.php:106
* @route '/api/courier/shift-summary'
*/
export const courierShiftSummary = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: courierShiftSummary.url(options),
    method: 'get',
})

courierShiftSummary.definition = {
    methods: ["get","head"],
    url: '/api/courier/shift-summary',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::courierShiftSummary
* @see app/Http/Controllers/DashboardController.php:106
* @route '/api/courier/shift-summary'
*/
courierShiftSummary.url = (options?: RouteQueryOptions) => {
    return courierShiftSummary.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::courierShiftSummary
* @see app/Http/Controllers/DashboardController.php:106
* @route '/api/courier/shift-summary'
*/
courierShiftSummary.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: courierShiftSummary.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::courierShiftSummary
* @see app/Http/Controllers/DashboardController.php:106
* @route '/api/courier/shift-summary'
*/
courierShiftSummary.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: courierShiftSummary.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::stats
* @see app/Http/Controllers/DashboardController.php:19
* @route '/api/dashboard/stats'
*/
export const stats = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})

stats.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::stats
* @see app/Http/Controllers/DashboardController.php:19
* @route '/api/dashboard/stats'
*/
stats.url = (options?: RouteQueryOptions) => {
    return stats.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::stats
* @see app/Http/Controllers/DashboardController.php:19
* @route '/api/dashboard/stats'
*/
stats.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::stats
* @see app/Http/Controllers/DashboardController.php:19
* @route '/api/dashboard/stats'
*/
stats.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stats.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::recentOrders
* @see app/Http/Controllers/DashboardController.php:33
* @route '/api/dashboard/recent-orders'
*/
export const recentOrders = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recentOrders.url(options),
    method: 'get',
})

recentOrders.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/recent-orders',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::recentOrders
* @see app/Http/Controllers/DashboardController.php:33
* @route '/api/dashboard/recent-orders'
*/
recentOrders.url = (options?: RouteQueryOptions) => {
    return recentOrders.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::recentOrders
* @see app/Http/Controllers/DashboardController.php:33
* @route '/api/dashboard/recent-orders'
*/
recentOrders.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recentOrders.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::recentOrders
* @see app/Http/Controllers/DashboardController.php:33
* @route '/api/dashboard/recent-orders'
*/
recentOrders.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: recentOrders.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::financialHistory
* @see app/Http/Controllers/DashboardController.php:43
* @route '/api/dashboard/financial-history'
*/
export const financialHistory = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: financialHistory.url(options),
    method: 'get',
})

financialHistory.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/financial-history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::financialHistory
* @see app/Http/Controllers/DashboardController.php:43
* @route '/api/dashboard/financial-history'
*/
financialHistory.url = (options?: RouteQueryOptions) => {
    return financialHistory.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::financialHistory
* @see app/Http/Controllers/DashboardController.php:43
* @route '/api/dashboard/financial-history'
*/
financialHistory.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: financialHistory.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::financialHistory
* @see app/Http/Controllers/DashboardController.php:43
* @route '/api/dashboard/financial-history'
*/
financialHistory.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: financialHistory.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::managersKpi
* @see app/Http/Controllers/DashboardController.php:66
* @route '/api/dashboard/managers-kpi'
*/
export const managersKpi = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: managersKpi.url(options),
    method: 'get',
})

managersKpi.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/managers-kpi',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::managersKpi
* @see app/Http/Controllers/DashboardController.php:66
* @route '/api/dashboard/managers-kpi'
*/
managersKpi.url = (options?: RouteQueryOptions) => {
    return managersKpi.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::managersKpi
* @see app/Http/Controllers/DashboardController.php:66
* @route '/api/dashboard/managers-kpi'
*/
managersKpi.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: managersKpi.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::managersKpi
* @see app/Http/Controllers/DashboardController.php:66
* @route '/api/dashboard/managers-kpi'
*/
managersKpi.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: managersKpi.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::couriersKpi
* @see app/Http/Controllers/DashboardController.php:86
* @route '/api/dashboard/couriers-kpi'
*/
export const couriersKpi = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: couriersKpi.url(options),
    method: 'get',
})

couriersKpi.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/couriers-kpi',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::couriersKpi
* @see app/Http/Controllers/DashboardController.php:86
* @route '/api/dashboard/couriers-kpi'
*/
couriersKpi.url = (options?: RouteQueryOptions) => {
    return couriersKpi.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::couriersKpi
* @see app/Http/Controllers/DashboardController.php:86
* @route '/api/dashboard/couriers-kpi'
*/
couriersKpi.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: couriersKpi.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::couriersKpi
* @see app/Http/Controllers/DashboardController.php:86
* @route '/api/dashboard/couriers-kpi'
*/
couriersKpi.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: couriersKpi.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::productsKpi
* @see app/Http/Controllers/DashboardController.php:119
* @route '/api/dashboard/products-kpi'
*/
export const productsKpi = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: productsKpi.url(options),
    method: 'get',
})

productsKpi.definition = {
    methods: ["get","head"],
    url: '/api/dashboard/products-kpi',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::productsKpi
* @see app/Http/Controllers/DashboardController.php:119
* @route '/api/dashboard/products-kpi'
*/
productsKpi.url = (options?: RouteQueryOptions) => {
    return productsKpi.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::productsKpi
* @see app/Http/Controllers/DashboardController.php:119
* @route '/api/dashboard/products-kpi'
*/
productsKpi.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: productsKpi.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::productsKpi
* @see app/Http/Controllers/DashboardController.php:119
* @route '/api/dashboard/products-kpi'
*/
productsKpi.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: productsKpi.url(options),
    method: 'head',
})

const DashboardController = { courierShiftSummary, stats, recentOrders, financialHistory, managersKpi, couriersKpi, productsKpi }

export default DashboardController