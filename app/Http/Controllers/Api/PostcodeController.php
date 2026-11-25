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
     *
     * @apiName GetPostcodes
     *
     * @apiGroup Postcodes
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Returns a paginated list of postcodes. Optionally filters
     * results by postcode value using the search query parameter.
     *
     * @apiQuery {String} [search] Search string for postcode value.
     * @apiQuery {Number} [page=1] Page number.
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
     * @apiError InternalServerError Fetch failed.
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
     *
     * @apiName CreatePostcode
     *
     * @apiGroup Postcodes
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Creates a postcode. Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiBody {Number} city_id City ID.
     * @apiBody {String} postcode Postcode value (maximum 4 characters).
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
     * @apiError Unauthorized Authentication required.
     * @apiError ValidationError Invalid request data.
     * @apiError InternalServerError Creation failed.
     */
    public function store(PostcodeRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $postcode = Postcode::create($validatedData);
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
     *
     * @apiName ShowPostcode
     *
     * @apiGroup Postcodes
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Returns a single postcode.
     *
     * @apiParam {Number} id Postcode record ID.
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
     * @apiError NotFound Postcode not found.
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
     *
     * @apiName UpdatePostcode
     *
     * @apiGroup Postcodes
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Updates a postcode. Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiParam {Number} id Postcode record ID.
     *
     * @apiBody {Number} [city_id] City ID.
     * @apiBody {String} [postcode] Postcode value (maximum 4 characters).
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
     * @apiError Unauthorized Authentication required.
     * @apiError NotFound Postcode not found.
     * @apiError ValidationError Invalid request data.
     * @apiError InternalServerError Update failed.
     */
    public function update(PostcodeRequest $request, string $id): JsonResponse
    {
        $postcode = Postcode::findOrFail($id);
        $validatedData = $request->validated();

        try {
            $postcode->update($validatedData);
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
     *
     * @apiName DeletePostcode
     *
     * @apiGroup Postcodes
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Deletes a postcode. Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiParam {Number} id Postcode record ID.
     *
     * @apiSuccess {String} message Success message.
     *
     * @apiError Unauthorized Authentication required.
     * @apiError NotFound Postcode not found.
     * @apiError InternalServerError Delete failed.
     */
    public function destroy(string $id): JsonResponse
    {
        $postcode = Postcode::find($id);

        if ($postcode) {
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
