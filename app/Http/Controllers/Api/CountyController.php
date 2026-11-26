<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountyRequest;
use App\Models\County;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CountyController extends Controller
{
    /**
     * @api {get} /api/counties List counties
     * @apiName GetCounties
     * @apiGroup Counties
     * @apiVersion 1.0.0
     * @apiDescription Returns a paginated list of counties sorted by name.
     * Optionally filters results by county name. The default page size is 20.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiQuery {String} [search] Case-insensitive substring to match in county names.
     * @apiQuery {Number} [page=1] Results page.
     *
     * @apiExample {curl} List counties
     * curl -X GET "https://kovacslaci.hu/zip-api/api/counties?search=pest&page=1" \
     *   -H "Accept: application/json"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json"
     * }
     *
     * @apiParamExample {json} Query parameters
     * {
     *   "search": "pest",
     *   "page": 1
     * }
     *
     * @apiSuccess {Object[]} data Counties list.
     * @apiSuccess {Number} data.id County ID.
     * @apiSuccess {String} data.name County name.
     * @apiSuccess {Object} pagination Pagination information.
     * @apiSuccess {Number} pagination.current_page Current page.
     * @apiSuccess {Number} pagination.last_page Last page.
     * @apiSuccess {Number} pagination.per_page Items per page.
     * @apiSuccess {Number} pagination.total Total items.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "data": [
     *     {
     *       "id": 13,
     *       "name": "Pest"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 20,
     *     "total": 1
     *   }
     * }
     *
     * @apiError (500) InternalServerError Fetch failed.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        try {
            $query = County::query()->orderBy('name');

            if ($search !== '') {
                $query->where('name', 'like', "%{$search}%");
            }

            $counties = $query->paginate(config('app.pagination_limit', 20));

            return response()->json([
                'data' => $counties->items(),
                'pagination' => [
                    'current_page' => $counties->currentPage(),
                    'last_page' => $counties->lastPage(),
                    'per_page' => $counties->perPage(),
                    'total' => $counties->total(),
                ],
            ]);
        } catch (QueryException $e) {
            Log::error('Error fetching counties: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.fetch_failed', ['model' => Str::plural('County')]), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected error in fetching counties: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.unexpected_error'), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {post} /api/counties Create county
     * @apiName CreateCounty
     * @apiGroup Counties
     * @apiVersion 1.0.0
     * @apiDescription Creates a county. Requires a valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiBody {String} name County name (required).
     *
     * @apiExample {curl} Create county
     * curl -X POST "https://kovacslaci.hu/zip-api/api/counties" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"name":"Pest"}'
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Content-Type": "application/json",
     *   "Authorization": "Bearer 1|your-token"
     * }
     *
     * @apiParamExample {json} Request body
     * {
     *   "name": "Pest"
     * }
     *
     * @apiSuccess (201) {Object} data Created county.
     * @apiSuccess (201) {Number} data.id County ID.
     * @apiSuccess (201) {String} data.name County name.
     * @apiSuccess (201) {String} message Success message.
     *
     * @apiSuccessExample {json} Created
     * {
     *   "data": {
     *     "id": 13,
     *     "name": "Pest"
     *   },
     *   "message": "County created successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (422) ValidationError County name is required.
     * @apiError (500) InternalServerError Creation failed.
     *
     * @apiErrorExample {json} Unauthorized
     * {
     *   "message": "Unauthenticated."
     * }
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The name field is required.",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     */
    public function store(CountyRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $county = County::create($validatedData);

            return response()->json([
                'data' => $county,
                'message' => __('skeletons.created_successfully', ['model' => 'County']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error creating county: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.create_failed', ['model' => 'County']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {get} /api/counties/:id Show county
     * @apiName ShowCounty
     * @apiGroup Counties
     * @apiVersion 1.0.0
     * @apiDescription Returns one county by its numeric ID.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiParam {Number} id County ID.
     *
     * @apiExample {curl} Show county
     * curl -X GET "https://kovacslaci.hu/zip-api/api/counties/13" \
     *   -H "Accept: application/json"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json"
     * }
     *
     * @apiParamExample {json} Path parameter
     * {
     *   "id": 13
     * }
     *
     * @apiSuccess {Object} data County details.
     * @apiSuccess {Number} data.id County ID.
     * @apiSuccess {String} data.name County name.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "data": {
     *     "id": 13,
     *     "name": "Pest"
     *   }
     * }
     *
     * @apiError (404) NotFound County not found.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "County not found."
     * }
     */
    public function show(string $id): JsonResponse
    {
        try {
            $county = County::findOrFail($id);

            return response()->json(['data' => $county]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'County'])], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @api {put} /api/counties/:id Update county
     * @apiName UpdateCounty
     * @apiGroup Counties
     * @apiVersion 1.0.0
     * @apiDescription Updates a county. Requires a valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id County ID.
     * @apiBody {String} [name] County name.
     *
     * @apiExample {curl} Update county
     * curl -X PUT "https://kovacslaci.hu/zip-api/api/counties/13" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"name":"Pest County"}'
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Content-Type": "application/json",
     *   "Authorization": "Bearer 1|your-token"
     * }
     *
     * @apiParamExample {json} Path parameter
     * {
     *   "id": 13
     * }
     * @apiParamExample {json} Request body
     * {
     *   "name": "Pest County"
     * }
     *
     * @apiSuccess {Object} data Updated county.
     * @apiSuccess {Number} data.id County ID.
     * @apiSuccess {String} data.name County name.
     * @apiSuccess {String} message Success message.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "data": {
     *     "id": 13,
     *     "name": "Pest County"
     *   },
     *   "message": "County updated successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound County not found.
     * @apiError (500) InternalServerError Update failed.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "County not found."
     * }
     */
    public function update(CountyRequest $request, string $id): JsonResponse
    {
        $county = County::findOrFail($id);
        $validatedData = $request->validated();

        try {
            $county->update($validatedData);

            return response()->json([
                'data' => $county,
                'message' => __('skeletons.updated_successfully', ['model' => 'County']),
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating county {$id}: ".$e->getMessage());

            return response()->json(['message' => __('skeletons.update_failed', ['model' => 'County']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {delete} /api/counties/:id Delete county
     * @apiName DeleteCounty
     * @apiGroup Counties
     * @apiVersion 1.0.0
     * @apiDescription Deletes a county and its related cities and postcodes.
     * Requires a valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id County ID.
     *
     * @apiExample {curl} Delete county
     * curl -X DELETE "https://kovacslaci.hu/zip-api/api/counties/13" \
     *   -H "Accept: application/json" \
     *   -H "Authorization: Bearer 1|your-token"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json",
     *   "Authorization": "Bearer 1|your-token"
     * }
     *
     * @apiParamExample {json} Path parameter
     * {
     *   "id": 13
     * }
     *
     * @apiSuccess {String} message Success message.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "message": "County deleted successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound County not found.
     * @apiError (500) InternalServerError Delete failed.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "County not found."
     * }
     */
    public function destroy(string $id): JsonResponse
    {
        $county = County::find($id);

        if ($county) {
            try {
                $county->delete();

                return response()->json(['message' => __('skeletons.deleted_successfully', ['model' => 'County'])]);
            } catch (\Exception $e) {
                Log::error("Error deleting county {$id}: ".$e->getMessage());

                return response()->json(['message' => __('skeletons.delete_failed', ['model' => 'County']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'County'])], Response::HTTP_NOT_FOUND);
    }
}
