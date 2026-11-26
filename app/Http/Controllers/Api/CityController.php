<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Models\City;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CityController extends Controller
{
    /**
     * @api {get} /api/cities List cities
     * @apiName GetCities
     * @apiGroup Cities
     * @apiVersion 1.0.0
     * @apiDescription Returns cities sorted by name, including each city's
     * county and postcodes. The default page size is 20.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiQuery {String} [search] Search string for city name.
     * @apiQuery {Number} [county_id] Filter by an existing county ID.
     * @apiQuery {Number} [page=1] Results page.
     *
     * @apiExample {curl} List cities
     * curl -X GET "https://kovacslaci.hu/zip-api/api/cities?page=1" \
     *   -H "Accept: application/json"
     * @apiExample {curl} Search cities
     * curl -X GET "https://kovacslaci.hu/zip-api/api/cities?search=G%C3%B6d" \
     *   -H "Accept: application/json"
     * @apiExample {curl} Cities in a county
     * curl -X GET "https://kovacslaci.hu/zip-api/api/cities?county_id=13" \
     *   -H "Accept: application/json"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json"
     * }
     *
     * @apiParamExample {json} Query parameters
     * {
     *   "search": "Göd",
     *   "county_id": 13,
     *   "page": 1
     * }
     *
     * @apiSuccess {Object[]} data Cities list.
     * @apiSuccess {Number} data.id City ID.
     * @apiSuccess {Number} data.county_id County ID.
     * @apiSuccess {String} data.name City name.
     * @apiSuccess {Object} data.county County associated with the city.
     * @apiSuccess {Number} data.county.id County ID.
     * @apiSuccess {String} data.county.name County name.
     * @apiSuccess {Object[]} data.postcodes Postcodes for the city.
     * @apiSuccess {Number} data.postcodes.id Postcode record ID.
     * @apiSuccess {Number} data.postcodes.city_id City ID.
     * @apiSuccess {String} data.postcodes.postcode Postcode value.
     * @apiSuccess {Object} pagination Pagination information.
     * @apiSuccess {Number} pagination.current_page Current page.
     * @apiSuccess {Number} pagination.last_page Last page.
     * @apiSuccess {Number} pagination.per_page Items per page.
     * @apiSuccess {Number} pagination.total Total items.
     *
     * @apiSuccessExample {json} Search Response
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "county_id": 13,
     *       "name": "Göd",
     *       "county": {
     *         "id": 13,
     *         "name": "Pest"
     *       },
     *       "postcodes": [
     *         {
     *           "id": 1,
     *           "city_id": 1,
     *           "postcode": "2131"
     *         }
     *       ]
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
     * @apiError (422) ValidationError Search must be a string and county_id must refer to an existing county.
     * @apiError (500) InternalServerError Fetch failed.
     *
     * @apiErrorExample {json} Invalid County Filter
     * {
     *   "message": "The selected county id is invalid.",
     *   "errors": {
     *     "county_id": ["The selected county id is invalid."]
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string'],
            'county_id' => ['sometimes', 'required', 'integer', 'exists:counties,id'],
        ]);
        $search = trim($filters['search'] ?? '');
        $countyId = $filters['county_id'] ?? null;

        try {
            $query = City::query()
                ->with(['county', 'postcodes'])
                ->orderBy('name');

            if ($countyId !== null) {
                $query->where('county_id', $countyId);
            }

            if ($search !== '') {
                $query->where('name', 'like', "%{$search}%");
            }

            $cities = $query->paginate(
                config('app.pagination_limit', 20)
            );

            return response()->json([
                'data' => $cities->items(),
                'pagination' => [
                    'current_page' => $cities->currentPage(),
                    'last_page' => $cities->lastPage(),
                    'per_page' => $cities->perPage(),
                    'total' => $cities->total(),
                ],
            ]);
        } catch (QueryException $e) {
            Log::error('Error fetching cities: '.$e->getMessage());

            return response()->json([
                'message' => __('skeletons.fetch_failed', [
                    'model' => Str::plural('City'),
                ]),
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            Log::error('Unexpected error in fetching cities: '.$e->getMessage());

            return response()->json([
                'message' => __('skeletons.unexpected_error'),
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {post} /api/cities Create city
     * @apiName CreateCity
     * @apiGroup Cities
     * @apiVersion 1.0.0
     * @apiDescription Creates a city in an existing county. Requires a valid
     * Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiBody {Number} county_id Existing county ID (required).
     * @apiBody {String} name City name (required).
     *
     * @apiExample {curl} Create city
     * curl -X POST "https://kovacslaci.hu/zip-api/api/cities" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"county_id":13,"name":"Göd"}'
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
     *   "county_id": 13,
     *   "name": "Göd"
     * }
     *
     * @apiSuccess (201) {Object} data Created city.
     * @apiSuccess (201) {Number} data.id City ID.
     * @apiSuccess (201) {Number} data.county_id County ID.
     * @apiSuccess (201) {String} data.name City name.
     * @apiSuccess (201) {Object} data.county County associated with the city.
     * @apiSuccess (201) {Number} data.county.id County ID.
     * @apiSuccess (201) {String} data.county.name County name.
     * @apiSuccess (201) {String} message Success message.
     *
     * @apiSuccessExample {json} Success Response
     * {
     *   "data": {
     *     "id": 1,
     *     "county_id": 13,
     *     "name": "Göd",
     *     "county": {
     *       "id": 13,
     *       "name": "Pest"
     *     }
     *   },
     *   "message": "City created successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (422) ValidationError County ID is missing or does not exist, or city name is missing.
     * @apiError (500) InternalServerError Creation failed.
     *
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The name field is required.",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     */
    public function store(CityRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $city = City::create($validatedData);
            $city->load('county');

            return response()->json([
                'data' => $city,
                'message' => __('skeletons.created_successfully', ['model' => 'City']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error creating city: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.create_failed', ['model' => 'City']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {get} /api/cities/:id Show city
     * @apiName ShowCity
     * @apiGroup Cities
     * @apiVersion 1.0.0
     * @apiDescription Returns one city with its county by numeric ID.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiParam {Number} id City ID.
     *
     * @apiExample {curl} Show city
     * curl -X GET "https://kovacslaci.hu/zip-api/api/cities/1" \
     *   -H "Accept: application/json"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json"
     * }
     *
     * @apiParamExample {json} Path parameter
     * {
     *   "id": 1
     * }
     *
     * @apiSuccess {Object} data City details.
     * @apiSuccess {Number} data.id City ID.
     * @apiSuccess {Number} data.county_id County ID.
     * @apiSuccess {String} data.name City name.
     * @apiSuccess {Object} data.county County associated with the city.
     * @apiSuccess {Number} data.county.id County ID.
     * @apiSuccess {String} data.county.name County name.
     *
     * @apiSuccessExample {json} Success Response
     * {
     *   "data": {
     *     "id": 1,
     *     "county_id": 13,
     *     "name": "Göd",
     *     "county": {
     *       "id": 13,
     *       "name": "Pest"
     *     }
     *   }
     * }
     *
     * @apiError (404) NotFound City not found.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "City not found."
     * }
     */
    public function show(string $id): JsonResponse
    {
        try {
            $city = City::with('county')->findOrFail($id);

            return response()->json(['data' => $city]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'City'])], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @api {put} /api/cities/:id Update city
     * @apiName UpdateCity
     * @apiGroup Cities
     * @apiVersion 1.0.0
     * @apiDescription Updates an existing city's name and/or county. Requires
     * a valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id City ID.
     * @apiBody {Number} [county_id] County ID.
     * @apiBody {String} [name] City name.
     *
     * @apiExample {curl} Update city
     * curl -X PUT "https://kovacslaci.hu/zip-api/api/cities/1" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"county_id":13,"name":"Göd"}'
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
     *   "id": 1
     * }
     * @apiParamExample {json} Request body
     * {
     *   "county_id": 13,
     *   "name": "Göd"
     * }
     *
     * @apiSuccess {Object} data Updated city.
     * @apiSuccess {Number} data.id City ID.
     * @apiSuccess {Number} data.county_id County ID.
     * @apiSuccess {String} data.name City name.
     * @apiSuccess {Object} data.county County associated with the city.
     * @apiSuccess {Number} data.county.id County ID.
     * @apiSuccess {String} data.county.name County name.
     * @apiSuccess {String} message Success message.
     *
     * @apiSuccessExample {json} Success Response
     * {
     *   "data": {
     *     "id": 1,
     *     "county_id": 13,
     *     "name": "Göd",
     *     "county": {
     *       "id": 13,
     *       "name": "Pest"
     *     }
     *   },
     *   "message": "City updated successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound City not found.
     * @apiError (500) InternalServerError Update failed.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "City not found."
     * }
     */
    public function update(CityRequest $request, string $id): JsonResponse
    {
        $city = City::findOrFail($id);
        $validatedData = $request->validated();

        try {
            $city->update($validatedData);
            $city->load('county');

            return response()->json([
                'data' => $city,
                'message' => __('skeletons.updated_successfully', ['model' => 'City']),
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating city {$id}: ".$e->getMessage());

            return response()->json(['message' => __('skeletons.update_failed', ['model' => 'City']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {delete} /api/cities/:id Delete city
     * @apiName DeleteCity
     * @apiGroup Cities
     * @apiVersion 1.0.0
     * @apiDescription Deletes a city and its postcodes. Requires a valid
     * Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id City ID.
     *
     * @apiExample {curl} Delete city
     * curl -X DELETE "https://kovacslaci.hu/zip-api/api/cities/1" \
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
     *   "id": 1
     * }
     *
     * @apiSuccess {String} message Success message.
     *
     * @apiSuccessExample {json} Success Response
     * {
     *   "message": "City deleted successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound City not found.
     * @apiError (500) InternalServerError Delete failed.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "City not found."
     * }
     */
    public function destroy(string $id): JsonResponse
    {
        $city = City::find($id);

        if ($city) {
            try {
                $city->delete();

                return response()->json(['message' => __('skeletons.deleted_successfully', ['model' => 'City'])]);
            } catch (\Exception $e) {
                Log::error("Error deleting city {$id}: ".$e->getMessage());

                return response()->json(['message' => __('skeletons.delete_failed', ['model' => 'City']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'City'])], Response::HTTP_NOT_FOUND);
    }
}
