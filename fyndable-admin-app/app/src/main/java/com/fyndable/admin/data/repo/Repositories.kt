package com.fyndable.admin.data.repo

import com.fyndable.admin.data.remote.ApiService
import com.fyndable.admin.data.remote.AiModelsResponse
import com.fyndable.admin.data.remote.GenerateLicenseRequest
import com.fyndable.admin.data.remote.GenerateLicenseResult
import com.fyndable.admin.data.remote.GeoScanDetailResponse
import com.fyndable.admin.data.remote.GeoScanResponse
import com.fyndable.admin.data.remote.License
import com.fyndable.admin.data.remote.LicenseDetailResponse
import com.fyndable.admin.data.remote.LicenseListResponse
import com.fyndable.admin.data.remote.LicenseStats
import com.fyndable.admin.data.remote.LicenseStatsResponse
import com.fyndable.admin.data.remote.PingResponse
import com.fyndable.admin.data.remote.RecentGeoScansResponse
import com.fyndable.admin.data.remote.RefreshAiModelsResponse
import com.fyndable.admin.data.remote.ReplyTicketRequest
import com.fyndable.admin.data.remote.RevenueStats
import com.fyndable.admin.data.remote.RunGeoScanRequest
import com.fyndable.admin.data.remote.SaveAiModelsRequest
import com.fyndable.admin.data.remote.SaveAiModelsResponse
import com.fyndable.admin.data.remote.Ticket
import com.fyndable.admin.data.remote.TicketListResponse
import com.fyndable.admin.data.remote.Tenant
import com.fyndable.admin.data.remote.TenantDetailResponse
import com.fyndable.admin.data.remote.TenantListResponse
import com.fyndable.admin.data.remote.UpdateLicenseRequest
import com.fyndable.admin.data.remote.UpdateTicketRequest
import com.fyndable.admin.data.remote.UsageHistoryResponse
import com.fyndable.admin.data.remote.UsageOverviewResponse
import kotlinx.serialization.json.Json

/**
 * Generic result wrapper for repository calls.
 */
sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Error(val message: String, val code: String? = null) : ApiResult<Nothing>()
}

/**
 * Parses a failed Retrofit response into a human-readable error message.
 * The WP REST API returns `{ "success": false, "error": "...", "message": "..." }`.
 */
private fun <T> parseError(response: retrofit2.Response<T>): String {
    val raw = response.errorBody()?.string()
    if (raw.isNullOrBlank()) {
        return "HTTP ${response.code()}"
    }
    return try {
        val parsed = Json { ignoreUnknownKeys = true }.decodeFromString(
            com.fyndable.admin.data.remote.ApiError.serializer(), raw
        )
        parsed.message ?: parsed.error ?: "HTTP ${response.code()}"
    } catch (_: Exception) {
        "HTTP ${response.code()}"
    }
}

private fun <T> handle(response: retrofit2.Response<T>): ApiResult<T> {
    return if (response.isSuccessful) {
        val body = response.body()
        if (body != null) ApiResult.Success(body) else ApiResult.Error("Empty response")
    } else {
        ApiResult.Error(parseError(response))
    }
}

class LicenseRepository(private val api: ApiService) {
    suspend fun ping(): ApiResult<PingResponse> = handle(api.ping())
    suspend fun getStats(): ApiResult<LicenseStatsResponse> = handle(api.getLicenseStats())
    suspend fun list(
        status: String? = null, type: String? = null, tier: String? = null,
        search: String? = null, limit: Int = 50, offset: Int = 0,
    ): ApiResult<LicenseListResponse> = handle(api.listLicenses(status, type, tier, search, limit, offset))
    suspend fun generate(req: GenerateLicenseRequest): ApiResult<GenerateLicenseResult> = handle(api.generateLicenses(req))
    suspend fun get(key: String): ApiResult<LicenseDetailResponse> = handle(api.getLicense(key))
    suspend fun update(key: String, req: UpdateLicenseRequest): ApiResult<LicenseDetailResponse> = handle(api.updateLicense(key, req))
    suspend fun revoke(key: String, reason: String): ApiResult<LicenseDetailResponse> =
        handle(api.revokeLicense(key, com.fyndable.admin.data.remote.RevokeLicenseRequest(reason)))
}

class TenantRepository(private val api: ApiService) {
    suspend fun list(
        status: String? = null, tier: String? = null, search: String? = null,
        limit: Int = 100, offset: Int = 0,
    ): ApiResult<TenantListResponse> = handle(api.listTenants(status, tier, search, limit, offset))
    suspend fun get(tenantKey: String): ApiResult<TenantDetailResponse> = handle(api.getTenant(tenantKey))
    suspend fun usageHistory(tenantKey: String, months: Int = 12): ApiResult<UsageHistoryResponse> =
        handle(api.getTenantUsageHistory(tenantKey, months))
}

class UsageRepository(private val api: ApiService) {
    suspend fun overview(): ApiResult<UsageOverviewResponse> = handle(api.getUsageOverview())
    suspend fun revenue(): ApiResult<com.fyndable.admin.data.remote.RevenueStatsResponse> = handle(api.getRevenueStats())
}

class SupportRepository(private val api: ApiService) {
    suspend fun list(
        status: String? = null, priority: String? = null, search: String? = null,
    ): ApiResult<TicketListResponse> = handle(api.listTickets(status, priority, search))
    suspend fun get(id: Int): ApiResult<com.fyndable.admin.data.remote.TicketResponse> = handle(api.getTicket(id))
    suspend fun update(id: Int, req: UpdateTicketRequest): ApiResult<com.fyndable.admin.data.remote.TicketResponse> =
        handle(api.updateTicket(id, req))
    suspend fun reply(id: Int, req: ReplyTicketRequest): ApiResult<com.fyndable.admin.data.remote.TicketResponse> =
        handle(api.replyTicket(id, req))
}

class GeoScanRepository(private val api: ApiService) {
    suspend fun run(url: String, keywords: List<String>, language: String): ApiResult<GeoScanResponse> =
        handle(api.runGeoScan(RunGeoScanRequest(url, keywords, language)))
    suspend fun recent(limit: Int = 20): ApiResult<RecentGeoScansResponse> = handle(api.recentGeoScans(limit))
    suspend fun get(id: Int): ApiResult<GeoScanDetailResponse> = handle(api.getGeoScan(id))
}

class AiModelRepository(private val api: ApiService) {
    suspend fun get(): ApiResult<AiModelsResponse> = handle(api.getAiModels())
    suspend fun save(req: SaveAiModelsRequest): ApiResult<SaveAiModelsResponse> = handle(api.saveAiModels(req))
    suspend fun refresh(): ApiResult<RefreshAiModelsResponse> = handle(api.refreshAiModels())
}
