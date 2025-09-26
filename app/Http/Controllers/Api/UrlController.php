<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteUrlRequest;
use App\Http\Requests\IndexUrlRequest;
use App\Http\Requests\StoreUrlRequest;
use App\Http\Requests\UpdateUrlRequest;
use App\Models\Url;
use App\Services\LoggingService;
use App\Services\UrlValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="URLs",
 *     description="API Endpoints para gestión de URLs acortadas"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="DeviceID",
 *     type="apiKey",
 *     in="header",
 *     name="X-Device-ID",
 *     description="Identificador único del dispositivo para asociar URLs"
 * )
 */
class UrlController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/urls",
     *     operationId="getUrls",
     *     tags={"URLs"},
     *     summary="Listar URLs del dispositivo",
     *     description="Obtiene una lista paginada de todas las URLs acortadas asociadas al dispositivo actual. Permite búsqueda por URL original y paginación.",
     *     security={{"DeviceID":{}}},
     *     @OA\Parameter(
     *         name="X-Device-ID",
     *         in="header",
     *         required=true,
     *         description="Identificador único del dispositivo",
     *         @OA\Schema(type="string", example="device_abc123")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Filtrar URLs por contenido de la URL original",
     *         @OA\Schema(type="string", example="example.com")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Número de URLs por página (1-100)",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Número de página",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de URLs obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=3),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=42),
     *             @OA\Property(property="from", type="integer", example=1),
     *             @OA\Property(property="to", type="integer", example=15),
     *             @OA\Property(property="first_page_url", type="string", example="http://localhost/api/urls?page=1"),
     *             @OA\Property(property="last_page_url", type="string", example="http://localhost/api/urls?page=3"),
     *             @OA\Property(property="next_page_url", type="string", example="http://localhost/api/urls?page=2"),
     *             @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="original_url", type="string", example="https://example.com/very-long-url"),
     *                     @OA\Property(property="short_code", type="string", example="abc123"),
     *                     @OA\Property(property="device_id", type="string", example="device_abc123"),
     *                     @OA\Property(property="clicks", type="integer", example=42),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-12-01T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-12-01T15:45:00.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Device ID requerido",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Device ID required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación en parámetros",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="No puede mostrar más de 100 elementos por página.")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos.")
     *         )
     *     )
     * )
     */
    public function index(IndexUrlRequest $request)
    {
        $query = Url::where('device_id', $request->getDeviceId());

        // Add search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('original_url', 'like', "%{$search}%");
        }

        // Add pagination
        $perPage = $request->input('per_page', 15);
        $urls = $query->latest()->paginate($perPage);

        return response()->json($urls);
    }

    /**
     * @OA\Post(
     *     path="/api/urls",
     *     operationId="createUrl",
     *     tags={"URLs"},
     *     summary="Crear URL acortada",
     *     description="Crea una nueva URL acortada a partir de una URL original. La URL debe cumplir con los estándares RFC 1738. El sistema automáticamente sanitiza y normaliza la URL antes de almacenarla.",
     *     security={{"DeviceID":{}}},
     *     @OA\Parameter(
     *         name="X-Device-ID",
     *         in="header",
     *         required=true,
     *         description="Identificador único del dispositivo",
     *         @OA\Schema(type="string", example="device_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos de la URL a acortar",
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(
     *                 property="url",
     *                 type="string",
     *                 maxLength=2048,
     *                 description="URL original que se desea acortar. Debe ser válida según RFC 1738",
     *                 example="https://example.com/very-long-url-that-needs-to-be-shortened"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="URL acortada creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="short_url", type="string", description="URL acortada completa", example="http://localhost/abc123"),
     *             @OA\Property(property="original_url", type="string", description="URL original normalizada", example="https://example.com/very-long-url-that-needs-to-be-shortened"),
     *             @OA\Property(property="code", type="string", description="Código corto único", example="abc123"),
     *             @OA\Property(property="sanitized", type="boolean", description="Indica si la URL fue sanitizada", example=false),
     *             @OA\Property(property="normalized", type="boolean", description="Indica si la URL fue normalizada", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Device ID requerido",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Device ID required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación de la URL",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="url",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"La URL es requerida.", "La URL no cumple con los estándares RFC 1738. Formato de URL inválido."}
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos.")
     *         )
     *     )
     * )
     */
    public function store(StoreUrlRequest $request)
    {
        $startTime = microtime(true);

        // Validation and authorization are handled by StoreUrlRequest
        $loggingService = new LoggingService();
        $urlValidatorService = new UrlValidatorService();

        // Log validation for monitoring
        $validationResult = $urlValidatorService->validateUrl($request->url);
        if (!$validationResult['valid']) {
            $loggingService->logUrlValidationEdgeCase($request->url, $validationResult, 'store_request');
        }

        $sanitizedUrl = $urlValidatorService->sanitizeUrl($request->url);
        $normalizedUrl = $urlValidatorService->normalizeUrl($sanitizedUrl);

        // Log RFC 1738 processing
        $loggingService->logRfc1738ProcessingAnomaly($request->url, $normalizedUrl, [
            'sanitized' => $sanitizedUrl !== $request->url,
            'normalized' => $normalizedUrl !== $sanitizedUrl
        ]);

        $url = Url::create([
            'original_url' => $normalizedUrl,
            'short_code' => Url::generateShortCode(),
            'device_id' => $request->getDeviceId(),
        ]);

        // Log performance metrics
        $duration = microtime(true) - $startTime;
        $loggingService->logPerformanceMetrics('url_creation', $duration, [
            'original_length' => strlen($request->url),
            'final_length' => strlen($normalizedUrl),
            'device_id' => $request->getDeviceId()
        ]);

        // Log successful operation
        $loggingService->logSuccessfulOperation('create_url', [
            'short_code' => $url->short_code,
            'processing_applied' => $sanitizedUrl !== $request->url || $normalizedUrl !== $sanitizedUrl
        ]);

        return response()->json([
            'short_url' => url($url->short_code),
            'original_url' => $url->original_url,
            'code' => $url->short_code,
            'sanitized' => $sanitizedUrl !== $request->url,
            'normalized' => $normalizedUrl !== $sanitizedUrl
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/{code}",
     *     operationId="redirectToUrl",
     *     tags={"URLs"},
     *     summary="Redireccionar a URL original",
     *     description="Redirecciona al usuario a la URL original usando el código corto. Incrementa el contador de clics y utiliza caché para mejorar el rendimiento.",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Código corto de la URL",
     *         @OA\Schema(type="string", minLength=6, maxLength=10, example="abc123")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirección exitosa a la URL original",
     *         @OA\Header(
     *             header="Location",
     *             description="URL de destino",
     *             @OA\Schema(type="string", example="https://example.com/destination")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Código corto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontró la URL solicitada.")
     *         )
     *     )
     * )
     */
    public function show(string $code)
    {
        $startTime = microtime(true);
        $loggingService = new LoggingService();

        $url = Cache::remember("url_{$code}", 3600, function () use ($code) {
            return Url::where('short_code', $code)->first();
        });

        if (!$url) {
            $loggingService->logFailedRedirection($code, 'URL not found in database');
            abort(404);
        }

        // Log performance for cache hit/miss
        $duration = microtime(true) - $startTime;
        if ($duration > 0.1) { // Log if redirect takes more than 100ms
            $loggingService->logPerformanceMetrics('url_redirect', $duration, [
                'short_code' => $code,
                'cached' => Cache::has("url_{$code}")
            ]);
        }

        $url->increment('clicks');

        // Log successful redirect
        $loggingService->logSuccessfulOperation('redirect', [
            'short_code' => $code,
            'clicks' => $url->clicks + 1,
            'target_url_length' => strlen($url->original_url)
        ]);

        return redirect($url->original_url, 302);
    }

    /**
     * @OA\Put(
     *     path="/api/urls/{id}",
     *     operationId="updateUrl",
     *     tags={"URLs"},
     *     summary="Actualizar URL acortada",
     *     description="Actualiza la URL original de una URL acortada existente. Solo el propietario (mismo Device ID) puede actualizar la URL. La nueva URL debe cumplir con RFC 1738.",
     *     security={{"DeviceID":{}}},
     *     @OA\Parameter(
     *         name="X-Device-ID",
     *         in="header",
     *         required=true,
     *         description="Identificador único del dispositivo propietario",
     *         @OA\Schema(type="string", example="device_abc123")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID único de la URL a actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nueva URL de destino",
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(
     *                 property="url",
     *                 type="string",
     *                 maxLength=2048,
     *                 description="Nueva URL original que debe cumplir RFC 1738",
     *                 example="https://newexample.com/updated-destination"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="original_url", type="string", example="https://newexample.com/updated-destination"),
     *             @OA\Property(property="short_code", type="string", example="abc123"),
     *             @OA\Property(property="device_id", type="string", example="device_abc123"),
     *             @OA\Property(property="clicks", type="integer", example=42),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2023-12-01T10:30:00.000000Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2023-12-01T15:45:00.000000Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Device ID requerido",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Device ID required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado para actualizar esta URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized to update this URL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="URL no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="URL not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación de la nueva URL",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="url",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"La URL no cumple con los estándares RFC 1738."}
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos.")
     *         )
     *     )
     * )
     */
    public function update(UpdateUrlRequest $request, string $id)
    {
        // Validation and authorization are handled by UpdateUrlRequest
        $url = Url::find($id);

        $urlValidatorService = new UrlValidatorService();
        $sanitizedUrl = $urlValidatorService->sanitizeUrl($request->url);
        $normalizedUrl = $urlValidatorService->normalizeUrl($sanitizedUrl);

        $url->update([
            'original_url' => $normalizedUrl,
        ]);

        return response()->json($url->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/api/urls/{id}",
     *     operationId="deleteUrl",
     *     tags={"URLs"},
     *     summary="Eliminar URL acortada",
     *     description="Elimina permanentemente una URL acortada y limpia su caché. Solo el propietario (mismo Device ID) puede eliminar la URL.",
     *     security={{"DeviceID":{}}},
     *     @OA\Parameter(
     *         name="X-Device-ID",
     *         in="header",
     *         required=true,
     *         description="Identificador único del dispositivo propietario",
     *         @OA\Schema(type="string", example="device_abc123")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID único de la URL a eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="URL eliminada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Device ID requerido",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Device ID required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado para eliminar esta URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized to delete this URL")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="URL no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="URL not found")
     *         )
     *     )
     * )
     */
    public function destroy(DeleteUrlRequest $request, string $id)
    {
        // Validation and authorization are handled by DeleteUrlRequest
        $url = $request->getUrl();

        Cache::forget("url_{$url->short_code}");
        $url->delete();

        return response()->json([], 204);
    }
}
