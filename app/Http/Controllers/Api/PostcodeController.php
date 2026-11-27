<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostcodeRequest;
use App\Models\Postcode;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PostcodeController extends Controller
{
    /**
     * @api {get} /api/postcodes List postcodes
     * @apiName GetPostcodes
     * @apiGroup Postcodes
     * @apiVersion 1.0.0
     * @apiDescription Returns postcodes sorted by postal code, including their
     * city and county. The default page size is 20.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiQuery {String} [search] Search string for postcode value.
     * @apiQuery {Number} [page=1] Results page.
     *
     * @apiExample {curl} List postcodes
     * curl -X GET "https://kovacslaci.hu/zip-api/api/postcodes?search=6000&page=1" \
     *   -H "Accept: application/json"
     *
     * @apiHeaderExample {json} Request headers
     * {
     *   "Accept": "application/json"
     * }
     *
     * @apiParamExample {json} Query parameters
     * {
     *   "search": "6000",
     *   "page": 1
     * }
     *
     * @apiSuccess {Object[]} data Postcodes list.
     * @apiSuccess {Number} data.id Postcode record ID.
     * @apiSuccess {Number} data.city_id City ID.
     * @apiSuccess {String} data.postcode Postcode value.
     * @apiSuccess {Object} data.city City associated with the postcode.
     * @apiSuccess {Number} data.city.id City ID.
     * @apiSuccess {Number} data.city.county_id County ID.
     * @apiSuccess {String} data.city.name City name.
     * @apiSuccess {Object} data.city.county County associated with the city.
     * @apiSuccess {Number} data.city.county.id County ID.
     * @apiSuccess {String} data.city.county.name County name.
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
     *       "id": 1,
     *       "city_id": 1,
     *       "postcode": "6000",
     *       "city": {
     *         "id": 1,
     *         "county_id": 3,
     *         "name": "Kecskemét",
     *         "county": {
     *           "id": 3,
     *           "name": "Bács-Kiskun"
     *         }
     *       }
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
            $query = Postcode::query()
                ->with('city.county')
                ->orderBy('postcode');

            if ($search !== '') {
                $query->where('postcode', 'like', "%{$search}%");
            }

            $postcodes = $query->paginate(config('app.pagination_limit', 20));

            return response()->json([
                'data' => $postcodes->items(),
                'pagination' => [
                    'current_page' => $postcodes->currentPage(),
                    'last_page' => $postcodes->lastPage(),
                    'per_page' => $postcodes->perPage(),
                    'total' => $postcodes->total(),
                ],
            ]);
        } catch (QueryException $e) {
            Log::error('Error fetching postcodes: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.fetch_failed', ['model' => Str::plural('Postcode')]), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected error in fetching postcodes: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.unexpected_error'), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {post} /api/postcodes Create postcode
     * @apiName CreatePostcode
     * @apiGroup Postcodes
     * @apiVersion 1.0.0
     * @apiDescription Creates a postcode for an existing city. Requires a
     * valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiBody {Number} city_id Existing city ID (required).
     * @apiBody {String} postcode Postcode value (required, maximum 4 characters).
     *
     * @apiExample {curl} Create postcode
     * curl -X POST "https://kovacslaci.hu/zip-api/api/postcodes" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"city_id":1,"postcode":"6000"}'
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
     *   "city_id": 1,
     *   "postcode": "6000"
     * }
     *
     * @apiSuccess (201) {Object} data Created postcode.
     * @apiSuccess (201) {Number} data.id Postcode record ID.
     * @apiSuccess (201) {Number} data.city_id City ID.
     * @apiSuccess (201) {String} data.postcode Postcode value.
     * @apiSuccess (201) {Object} data.city City associated with the postcode.
     * @apiSuccess (201) {Number} data.city.id City ID.
     * @apiSuccess (201) {Number} data.city.county_id County ID.
     * @apiSuccess (201) {String} data.city.name City name.
     * @apiSuccess (201) {Object} data.city.county County associated with the city.
     * @apiSuccess (201) {Number} data.city.county.id County ID.
     * @apiSuccess (201) {String} data.city.county.name County name.
     * @apiSuccess (201) {String} message Success message.
     *
     * @apiSuccessExample {json} Created
     * {
     *   "data": {
     *     "id": 1,
     *     "city_id": 1,
     *     "postcode": "6000",
     *     "city": {
     *       "id": 1,
     *       "county_id": 3,
     *       "name": "Kecskemét",
     *       "county": {
     *         "id": 3,
     *         "name": "Bács-Kiskun"
     *       }
     *     }
     *   },
     *   "message": "Postcode created successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (422) ValidationError City ID is missing or invalid, or postcode is missing or longer than four characters.
     * @apiError (500) InternalServerError Creation failed.
     *
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The postcode field is required.",
     *   "errors": {
     *     "postcode": ["The postcode field is required."]
     *   }
     * }
     */
    public function store(PostcodeRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $userId = $request->user()->getAuthIdentifier();
            $postcode = new Postcode($validatedData);
            $postcode->created_by = $userId;
            $postcode->updated_by = $userId;
            $postcode->save();
            $postcode->load('city.county');

            return response()->json([
                'data' => $postcode,
                'message' => __('skeletons.created_successfully', ['model' => 'Postcode']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Error creating postcode: '.$e->getMessage());

            return response()->json(['message' => __('skeletons.create_failed', ['model' => 'Postcode']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {get} /api/postcodes/:id Show postcode
     * @apiName ShowPostcode
     * @apiGroup Postcodes
     * @apiVersion 1.0.0
     * @apiDescription Returns one postcode with its city and county by numeric ID.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiParam {Number} id Postcode record ID.
     *
     * @apiExample {curl} Show postcode
     * curl -X GET "https://kovacslaci.hu/zip-api/api/postcodes/1" \
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
     * @apiSuccess {Object} data Postcode details.
     * @apiSuccess {Number} data.id Postcode record ID.
     * @apiSuccess {Number} data.city_id City ID.
     * @apiSuccess {String} data.postcode Postcode value.
     * @apiSuccess {Object} data.city City associated with the postcode.
     * @apiSuccess {Number} data.city.id City ID.
     * @apiSuccess {Number} data.city.county_id County ID.
     * @apiSuccess {String} data.city.name City name.
     * @apiSuccess {Object} data.city.county County associated with the city.
     * @apiSuccess {Number} data.city.county.id County ID.
     * @apiSuccess {String} data.city.county.name County name.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "data": {
     *     "id": 1,
     *     "city_id": 1,
     *     "postcode": "6000",
     *     "city": {
     *       "id": 1,
     *       "county_id": 3,
     *       "name": "Kecskemét",
     *       "county": {
     *         "id": 3,
     *         "name": "Bács-Kiskun"
     *       }
     *     }
     *   }
     * }
     *
     * @apiError (404) NotFound Postcode not found.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "Postcode not found."
     * }
     */
    public function show(string $id): JsonResponse
    {
        try {
            $postcode = Postcode::with('city.county')->findOrFail($id);

            return response()->json(['data' => $postcode]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'Postcode'])], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @api {put} /api/postcodes/:id Update postcode
     * @apiName UpdatePostcode
     * @apiGroup Postcodes
     * @apiVersion 1.0.0
     * @apiDescription Updates a postcode and/or its city. Requires a valid
     * Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Content-Type Send the request body as application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id Postcode record ID.
     * @apiBody {Number} [city_id] Existing city ID.
     * @apiBody {String} [postcode] Postcode value (maximum 4 characters).
     *
     * @apiExample {curl} Update postcode
     * curl -X PUT "https://kovacslaci.hu/zip-api/api/postcodes/1" \
     *   -H "Accept: application/json" \
     *   -H "Content-Type: application/json" \
     *   -H "Authorization: Bearer 1|your-token" \
     *   -d '{"postcode":"6001"}'
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
     *   "postcode": "6001"
     * }
     *
     * @apiSuccess {Object} data Updated postcode.
     * @apiSuccess {Number} data.id Postcode record ID.
     * @apiSuccess {Number} data.city_id City ID.
     * @apiSuccess {String} data.postcode Postcode value.
     * @apiSuccess {Object} data.city City associated with the postcode.
     * @apiSuccess {Number} data.city.id City ID.
     * @apiSuccess {Number} data.city.county_id County ID.
     * @apiSuccess {String} data.city.name City name.
     * @apiSuccess {Object} data.city.county County associated with the city.
     * @apiSuccess {Number} data.city.county.id County ID.
     * @apiSuccess {String} data.city.county.name County name.
     * @apiSuccess {String} message Success message.
     *
     * @apiSuccessExample {json} Success
     * {
     *   "data": {
     *     "id": 1,
     *     "city_id": 1,
     *     "postcode": "6001",
     *     "city": {
     *       "id": 1,
     *       "county_id": 3,
     *       "name": "Kecskemét",
     *       "county": {
     *         "id": 3,
     *         "name": "Bács-Kiskun"
     *       }
     *     }
     *   },
     *   "message": "Postcode updated successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound Postcode not found.
     * @apiError (403) Forbidden Core data cannot be modified.
     * @apiError (422) ValidationError Supplied city ID is invalid or postcode exceeds four characters.
     * @apiError (500) InternalServerError Update failed.
     *
     * @apiErrorExample {json} Validation Error
     * {
     *   "message": "The postcode field must not be greater than 4 characters.",
     *   "errors": {
     *     "postcode": ["The postcode field must not be greater than 4 characters."]
     *   }
     * }
     */
    public function update(PostcodeRequest $request, string $id): JsonResponse
    {
        $postcode = Postcode::findOrFail($id);
        abort_if($postcode->created_by === null, Response::HTTP_FORBIDDEN, __('skeletons.core_data_protected'));

        $validatedData = $request->validated();

        try {
            $postcode->fill($validatedData);
            $postcode->updated_by = $request->user()->getAuthIdentifier();
            $postcode->save();
            $postcode->load('city.county');

            return response()->json([
                'data' => $postcode,
                'message' => __('skeletons.updated_successfully', ['model' => 'Postcode']),
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating postcode {$id}: ".$e->getMessage());

            return response()->json(['message' => __('skeletons.update_failed', ['model' => 'Postcode']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @api {delete} /api/postcodes/:id Delete postcode
     * @apiName DeletePostcode
     * @apiGroup Postcodes
     * @apiVersion 1.0.0
     * @apiDescription Deletes a postcode. Requires a valid Sanctum bearer token.
     * @apiHeader {String} [Accept] Request JSON responses with application/json.
     * @apiHeader {String} Authorization Bearer access token (for example: Bearer 1|your-token).
     * @apiParam {Number} id Postcode record ID.
     *
     * @apiExample {curl} Delete postcode
     * curl -X DELETE "https://kovacslaci.hu/zip-api/api/postcodes/1" \
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
     * @apiSuccessExample {json} Success
     * {
     *   "message": "Postcode deleted successfully."
     * }
     *
     * @apiError (401) Unauthorized Authentication required or token invalid.
     * @apiError (404) NotFound Postcode not found.
     * @apiError (403) Forbidden Core data cannot be deleted.
     * @apiError (500) InternalServerError Delete failed.
     *
     * @apiErrorExample {json} Not Found
     * {
     *   "message": "Postcode not found."
     * }
     */
    public function destroy(string $id): JsonResponse
    {
        $postcode = Postcode::find($id);

        if ($postcode) {
            abort_if($postcode->created_by === null, Response::HTTP_FORBIDDEN, __('skeletons.core_data_protected'));

            try {
                $postcode->delete();

                return response()->json(['message' => __('skeletons.deleted_successfully', ['model' => 'Postcode'])]);
            } catch (\Exception $e) {
                Log::error("Error deleting postcode {$id}: ".$e->getMessage());

                return response()->json(['message' => __('skeletons.delete_failed', ['model' => 'Postcode']), 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json(['message' => __('skeletons.model_not_found', ['model' => 'Postcode'])], Response::HTTP_NOT_FOUND);
    }
}
