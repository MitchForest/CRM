<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Sassy CRM API",
 *     version="1.0.0",
 *     description="Modern CRM API for software sales teams",
 *     @OA\Contact(
 *         name="API Support",
 *         email="support@sassycrm.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="total", type="integer", example=100),
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="limit", type="integer", example=20),
 *     @OA\Property(property="total_pages", type="integer", example=5)
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="error", type="string", example="An error occurred"),
 *     @OA\Property(property="code", type="string", example="ERR_001"),
 *     @OA\Property(property="details", type="object")
 * )
 */
class OpenApiInfo
{
    // This class exists only for OpenAPI documentation
}