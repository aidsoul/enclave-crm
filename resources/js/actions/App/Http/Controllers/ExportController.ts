import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ExportController::exportFinancial
* @see app/Http/Controllers/ExportController.php:18
* @route '/api/exports/financial'
*/
export const exportFinancial = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportFinancial.url(options),
    method: 'get',
})

exportFinancial.definition = {
    methods: ["get","head"],
    url: '/api/exports/financial',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ExportController::exportFinancial
* @see app/Http/Controllers/ExportController.php:18
* @route '/api/exports/financial'
*/
exportFinancial.url = (options?: RouteQueryOptions) => {
    return exportFinancial.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ExportController::exportFinancial
* @see app/Http/Controllers/ExportController.php:18
* @route '/api/exports/financial'
*/
exportFinancial.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportFinancial.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ExportController::exportFinancial
* @see app/Http/Controllers/ExportController.php:18
* @route '/api/exports/financial'
*/
exportFinancial.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportFinancial.url(options),
    method: 'head',
})

const ExportController = { exportFinancial }

export default ExportController