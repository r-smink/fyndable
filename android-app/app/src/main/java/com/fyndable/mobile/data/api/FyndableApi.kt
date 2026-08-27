package com.fyndable.mobile.data.api

import com.fyndable.mobile.data.model.AddKeywordRequest
import com.fyndable.mobile.data.model.Cluster
import com.fyndable.mobile.data.model.ContentResult
import com.fyndable.mobile.data.model.CreatedPost
import com.fyndable.mobile.data.model.DeletePostRequest
import com.fyndable.mobile.data.model.GenerateClusterRequest
import com.fyndable.mobile.data.model.GenerateContentRequest
import com.fyndable.mobile.data.model.GenerateKeywordsRequest
import com.fyndable.mobile.data.model.Keyword
import com.fyndable.mobile.data.model.PostMetrics
import com.fyndable.mobile.data.model.PostStatsResponse
import com.fyndable.mobile.data.model.RankKeyword
import com.fyndable.mobile.data.model.UpdatePostRequest
import com.fyndable.mobile.data.model.WriteArticleRequest
import kotlinx.serialization.json.JsonElement
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query

interface FyndableApi {

    // ── Keywords ──
    @GET("keywords")
    suspend fun getKeywords(@Query("limit") limit: Int = 100): Response<JsonElement>

    @POST("keywords/add")
    suspend fun addKeyword(@Body body: AddKeywordRequest): Response<Keyword>

    @POST("keywords/generate")
    suspend fun generateKeywords(@Body body: GenerateKeywordsRequest): Response<JsonElement>

    // ── Clusters ──
    @GET("clusters/list")
    suspend fun getClusters(): Response<JsonElement>

    @GET("clusters/{id}")
    suspend fun getCluster(@Path("id") id: Int): Response<JsonElement>

    @POST("clusters/generate")
    suspend fun generateCluster(@Body body: GenerateClusterRequest): Response<JsonElement>

    @POST("clusters/generate-content")
    suspend fun generateClusterContent(@Body body: GenerateContentRequest): Response<ContentResult>

    // ── Content Writer ──
    @POST("write-article")
    suspend fun writeArticle(@Body body: WriteArticleRequest): Response<ContentResult>

    // ── Created Posts ──
    @GET("created-posts")
    suspend fun getCreatedPosts(
        @Query("per_page") perPage: Int = 50,
        @Query("post_status") postStatus: String? = null
    ): Response<JsonElement>

    @GET("created-posts/stats")
    suspend fun getPostStats(): Response<JsonElement>

    @GET("created-posts/{id}")
    suspend fun getPost(@Path("id") id: Int): Response<CreatedPost>

    @GET("created-posts/{id}/gsc-metrics")
    suspend fun getPostGscMetrics(@Path("id") id: Int): Response<PostMetrics>

    @PUT("created-posts/{id}")
    suspend fun updatePost(
        @Path("id") id: Int,
        @Body body: UpdatePostRequest
    ): Response<CreatedPost>

    @DELETE("created-posts/{id}")
    suspend fun deletePost(
        @Path("id") id: Int,
        @Body body: DeletePostRequest
    ): Response<Unit>

    // ── Ranks / Performance ──
    @GET("ranks/keywords")
    suspend fun getRanks(): Response<JsonElement>

    @POST("ranks/check-now")
    suspend fun checkRankNow(@Body body: Map<String, String>): Response<Unit>
}
