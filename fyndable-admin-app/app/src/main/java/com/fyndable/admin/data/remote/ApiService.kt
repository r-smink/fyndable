package com.fyndable.admin.data.remote

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface ApiService {

    // --- Ping ---
    @GET("admin/ping")
    suspend fun ping(): Response<PingResponse>

    // --- Licenses ---
    @GET("admin/licenses/stats")
    suspend fun getLicenseStats(): Response<LicenseStatsResponse>

    @GET("admin/licenses")
    suspend fun listLicenses(
        @Query("status") status: String? = null,
        @Query("type") type: String? = null,
        @Query("tier") tier: String? = null,
        @Query("search") search: String? = null,
        @Query("limit") limit: Int = 50,
        @Query("offset") offset: Int = 0,
    ): Response<LicenseListResponse>

    @POST("admin/licenses")
    suspend fun generateLicenses(@Body body: GenerateLicenseRequest): Response<GenerateLicenseResult>

    @GET("admin/licenses/{key}")
    suspend fun getLicense(@Path("key") key: String): Response<LicenseDetailResponse>

    @POST("admin/licenses/{key}")
    suspend fun updateLicense(
        @Path("key") key: String,
        @Body body: UpdateLicenseRequest,
    ): Response<LicenseDetailResponse>

    @POST("admin/licenses/{key}/revoke")
    suspend fun revokeLicense(
        @Path("key") key: String,
        @Body body: RevokeLicenseRequest,
    ): Response<LicenseDetailResponse>

    // --- Tenants / usage ---
    @GET("admin/tenants")
    suspend fun listTenants(
        @Query("status") status: String? = null,
        @Query("tier") tier: String? = null,
        @Query("search") search: String? = null,
        @Query("limit") limit: Int = 100,
        @Query("offset") offset: Int = 0,
    ): Response<TenantListResponse>

    @GET("admin/tenants/{tenant_key}")
    suspend fun getTenant(@Path("tenant_key") tenantKey: String): Response<TenantDetailResponse>

    @GET("admin/tenants/{tenant_key}/usage/history")
    suspend fun getTenantUsageHistory(
        @Path("tenant_key") tenantKey: String,
        @Query("months") months: Int = 12,
    ): Response<UsageHistoryResponse>

    @GET("admin/usage")
    suspend fun getUsageOverview(): Response<UsageOverviewResponse>

    @GET("admin/revenue/stats")
    suspend fun getRevenueStats(): Response<RevenueStatsResponse>

    // --- Support tickets ---
    @GET("admin/support/tickets")
    suspend fun listTickets(
        @Query("status") status: String? = null,
        @Query("priority") priority: String? = null,
        @Query("search") search: String? = null,
    ): Response<TicketListResponse>

    @GET("admin/support/tickets/{id}")
    suspend fun getTicket(@Path("id") id: Int): Response<TicketResponse>

    @POST("admin/support/tickets/{id}")
    suspend fun updateTicket(
        @Path("id") id: Int,
        @Body body: UpdateTicketRequest,
    ): Response<TicketResponse>

    @POST("admin/support/tickets/{id}/reply")
    suspend fun replyTicket(
        @Path("id") id: Int,
        @Body body: ReplyTicketRequest,
    ): Response<TicketResponse>

    // --- GEO Readiness scan ---
    @POST("admin/geo-scan")
    suspend fun runGeoScan(@Body body: RunGeoScanRequest): Response<GeoScanResponse>

    @GET("admin/geo-scan/recent")
    suspend fun recentGeoScans(@Query("limit") limit: Int = 20): Response<RecentGeoScansResponse>

    @GET("admin/geo-scan/{id}")
    suspend fun getGeoScan(@Path("id") id: Int): Response<GeoScanDetailResponse>

    // --- AI models ---
    @GET("admin/ai-models")
    suspend fun getAiModels(): Response<AiModelsResponse>

    @POST("admin/ai-models")
    suspend fun saveAiModels(@Body body: SaveAiModelsRequest): Response<SaveAiModelsResponse>

    @POST("admin/ai-models/refresh")
    suspend fun refreshAiModels(): Response<RefreshAiModelsResponse>
}
