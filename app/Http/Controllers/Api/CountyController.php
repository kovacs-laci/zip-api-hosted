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
     *
     * @apiName GetCounties
     *
     * @apiGroup Counties
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Returns a paginated list of counties. Optionally filters
     * results by county name using the search query parameter.
     *
     * @apiQuery {String} [search] Search string for county name.
     * @apiQuery {Number} [page=1] Page number.
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
     * @apiError InternalServerError Fetch failed.
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
     *
     * @apiName CreateCounty
     *
     * @apiGroup Counties
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Creates a county. Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiBody {String} name County name.
     *
     * @apiSuccess (201) {Object} data Created county.
     * @apiSuccess (201) {Number} data.id County ID.
     * @apiSuccess (201) {String} data.name County name.
     * @apiSuccess (201) {String} message Success message.
     *
     * @apiError Unauthorized Authentication required.
     * @apiError ValidationError Invalid request data.
     * @apiError InternalServerError Creation failed.
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
     *
     * @apiName ShowCounty
     *
     * @apiGroup Counties
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Returns a single county.
     *
     * @apiParam {Number} id County ID.
     *
     * @apiSuccess {Object} data County details.
     * @apiSuccess {Number} data.id County ID.
     * @apiSuccess {String} data.name County name.
     *
     * @apiError NotFound County not found.
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
     *
     * @apiName UpdateCounty
     *
     * @apiGroup Counties
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Updates a county. Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiParam {Number} id County ID.
     *
     * @apiBody {String} [name] County name.
     *
     * @apiSuccess {Object} data Updated county.
     * @apiSuccess {Number} data.id County ID.
     * @apiSuccess {String} data.name County name.
     * @apiSuccess {String} message Success message.
     *
     * @apiError Unauthorized Authentication required.
     * @apiError NotFound County not found.
     * @apiError ValidationError Invalid request data.
     * @apiError InternalServerError Update failed.
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
     *
     * @apiName DeleteCounty
     *
     * @apiGroup Counties
     *
     * @apiVersion 1.0.0
     *
     * @apiDescription Deletes a county and its cities and postcodes.
     * Authentication is required.
     *
     * @apiHeader {String} Authorization Bearer access token.
     *
     * @apiParam {Number} id County ID.
     *
     * @apiSuccess {String} message Success message.
     *
     * @apiError Unauthorized Authentication required.
     * @apiError NotFound County not found.
     * @apiError InternalServerError Delete failed.
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
