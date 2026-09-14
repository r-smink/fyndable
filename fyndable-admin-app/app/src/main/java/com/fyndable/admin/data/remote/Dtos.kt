package com.fyndable.admin.data.remote

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// ---------------------------------------------------------------------------
// Generic API envelope
// ---------------------------------------------------------------------------

@Serializable
data class ApiError(
    val success: Boolean = false,
    val error: String? = null,
    val message: String? = null,
)

// ---------------------------------------------------------------------------
// Ping / auth
// ---------------------------------------------------------------------------

@Serializable
data class PingResponse(
    val success: Boolean,
    val authenticated: Boolean,
    val user: PingUser? = null,
)

@Serializable
data class PingUser(
    val id: Int,
    val login: String,
    @SerialName("display_name") val displayName: String,
    val email: String,
)

// ---------------------------------------------------------------------------
// Licenses
// ---------------------------------------------------------------------------

@Serializable
data class LicenseStatsResponse(
    val success: Boolean,
    val stats: LicenseStats,
)

@Serializable
data class LicenseStats(
    val total: Int = 0,
    @SerialName("created_today") val createdToday: Int = 0,
    @SerialName("created_this_month") val createdThisMonth: Int = 0,
    @SerialName("by_status") val byStatus: List<StatusCount> = emptyList(),
    @SerialName("by_type") val byType: List<TypeCount> = emptyList(),
    @SerialName("by_tier") val byTier: List<TierCount> = emptyList(),
)

@Serializable
data class StatusCount(val status: String, val count: Int)
@Serializable
data class TypeCount(@SerialName("license_type") val licenseType: String, val count: Int)
@Serializable
data class TierCount(val tier: String, val count: Int)

@Serializable
data class LicenseListResponse(
    val success: Boolean,
    val licenses: List<License>,
    val total: Int,
    val limit: Int,
    val offset: Int,
)

@Serializable
data class License(
    @SerialName("license_key") val licenseKey: String,
    @SerialName("license_type") val licenseType: String = "",
    val tier: String = "",
    val status: String = "",
    @SerialName("max_sites") val maxSites: Int = 1,
    @SerialName("rate_limit") val rateLimit: Int = 0,
    @SerialName("api_calls_limit") val apiCallsLimit: Int = 0,
    @SerialName("expires_days") val expiresDays: Int? = null,
    @SerialName("expires_at") val expiresAt: String? = null,
    @SerialName("assigned_to") val assignedTo: String? = null,
    val notes: String? = null,
    @SerialName("created_at") val createdAt: String = "",
    @SerialName("activated_at") val activatedAt: String? = null,
    @SerialName("revoked_at") val revokedAt: String? = null,
    @SerialName("revoked_reason") val revokedReason: String? = null,
)

@Serializable
data class LicenseDetailResponse(
    val success: Boolean,
    val license: License,
    val tenant: Tenant? = null,
)

@Serializable
data class GenerateLicenseRequest(
    val count: Int = 1,
    val type: String = "paid",
    val tier: String = "starter",
    @SerialName("max_sites") val maxSites: Int = 1,
    @SerialName("rate_limit") val rateLimit: Int = 0,
    @SerialName("api_calls_limit") val apiCallsLimit: Int = 0,
    @SerialName("expires_days") val expiresDays: Int? = null,
    @SerialName("assigned_to") val assignedTo: String = "",
    val notes: String = "",
    @SerialName("key_prefix") val keyPrefix: String = "",
)

@Serializable
data class GenerateLicenseResult(
    val success: Boolean,
    val result: GenerateLicensePayload,
)

@Serializable
data class GenerateLicensePayload(
    @SerialName("license_key") val licenseKey: String? = null,
    val type: String? = null,
    val tier: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    val generated: Int? = null,
    val failed: Int? = null,
    val licenses: List<GeneratedKey>? = null,
)

@Serializable
data class GeneratedKey(
    @SerialName("license_key") val licenseKey: String,
    val type: String? = null,
    val tier: String? = null,
)

@Serializable
data class RevokeLicenseRequest(val reason: String = "")

@Serializable
data class UpdateLicenseRequest(
    @SerialName("assigned_to") val assignedTo: String? = null,
    val notes: String? = null,
    @SerialName("max_sites") val maxSites: Int? = null,
    @SerialName("rate_limit") val rateLimit: Int? = null,
    @SerialName("api_calls_limit") val apiCallsLimit: Int? = null,
    @SerialName("license_type") val licenseType: String? = null,
)

// ---------------------------------------------------------------------------
// Tenants
// ---------------------------------------------------------------------------

@Serializable
data class TenantListResponse(
    val success: Boolean,
    val tenants: List<Tenant>,
    val limit: Int,
    val offset: Int,
)

@Serializable
data class Tenant(
    @SerialName("tenant_key") val tenantKey: String,
    val name: String = "",
    val domain: String? = null,
    val email: String? = null,
    val tier: String = "",
    val status: String = "",
    @SerialName("max_sites") val maxSites: Int = 1,
    @SerialName("rate_limit") val rateLimit: Int = 0,
    @SerialName("api_calls_limit") val apiCallsLimit: Int = 0,
    @SerialName("expires_at") val expiresAt: String? = null,
    @SerialName("last_active") val lastActive: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class TenantDetailResponse(
    val success: Boolean,
    val tenant: Tenant,
    val usage: UsageData,
    val limits: TenantLimits,
    val onboarding: OnboardingInfo,
)

@Serializable
data class UsageData(
    @SerialName("api_calls") val apiCalls: Int = 0,
    @SerialName("api_cost") val apiCost: Double = 0.0,
    @SerialName("serp_requests") val serpRequests: Int = 0,
    @SerialName("content_generated") val contentGenerated: Int = 0,
    @SerialName("keywords_tracked") val keywordsTracked: Int = 0,
)

@Serializable
data class TenantLimits(
    val valid: Boolean = true,
    val error: String? = null,
    @Serializable(with = LimitChecksFlexibleSerializer::class)
    val checks: LimitChecks? = null,
)

@Serializable
data class LimitChecks(
    @Serializable(with = LimitCheckFlexibleSerializer::class)
    @SerialName("api_calls") val apiCalls: LimitCheck? = null,
    @Serializable(with = LimitCheckFlexibleSerializer::class)
    @SerialName("api_cost") val apiCost: LimitCheck? = null,
)

@Serializable
data class LimitCheck(
    val used: Int = 0,
    val limit: Int = 0,
    val exceeded: Boolean = false,
)

@Serializable
data class OnboardingInfo(
    val completed: Boolean = false,
    @SerialName("completed_at") val completedAt: String? = null,
)

@Serializable
data class UsageHistoryResponse(
    val success: Boolean,
    val history: List<UsageHistoryEntry>,
)

@Serializable
data class UsageHistoryEntry(
    val period: String,
    @SerialName("api_calls") val apiCalls: Int = 0,
    @SerialName("api_cost") val apiCost: Double = 0.0,
    @SerialName("serp_requests") val serpRequests: Int = 0,
    @SerialName("content_generated") val contentGenerated: Int = 0,
    @SerialName("keywords_tracked") val keywordsTracked: Int = 0,
)

@Serializable
data class UsageOverviewResponse(
    val success: Boolean,
    val tenants: List<UsageOverviewTenant>,
    val count: Int,
)

@Serializable
data class UsageOverviewTenant(
    @SerialName("tenant_key") val tenantKey: String,
    val name: String,
    val domain: String,
    val tier: String,
    val email: String,
    val usage: UsageData,
    @Serializable(with = LimitChecksFlexibleSerializer::class)
    val limits: LimitChecks? = null,
    val onboarding: OnboardingInfo,
)

@Serializable
data class RevenueStatsResponse(
    val success: Boolean,
    val stats: RevenueStats,
)

@Serializable
data class RevenueStats(
    val mrr: Double = 0.0,
    val arr: Double = 0.0,
    val currency: String = "EUR",
    @SerialName("total_tenants") val totalTenants: Int = 0,
    @SerialName("active_tenants") val activeTenants: Int = 0,
    @SerialName("trial_tenants") val trialTenants: Int = 0,
    @SerialName("paid_tenants") val paidTenants: Int = 0,
    @SerialName("new_this_month") val newThisMonth: Int = 0,
    @SerialName("churned_this_month") val churnedThisMonth: Int = 0,
    @SerialName("trial_conversion_rate") val trialConversionRate: Double = 0.0,
    @Serializable(with = FlexibleRevenueTierMapSerializer::class)
    @SerialName("revenue_by_tier") val revenueByTier: Map<String, RevenueTier> = emptyMap(),
    @SerialName("mrr_trend") val mrrTrend: List<MrrTrendPoint> = emptyList(),
)

@Serializable
data class RevenueTier(
    val tenants: Int = 0,
    @SerialName("unit_price") val unitPrice: Double = 0.0,
    val revenue: Double = 0.0,
)

@Serializable
data class MrrTrendPoint(
    val month: String,
    val mrr: Double,
)

// ---------------------------------------------------------------------------
// Support tickets
// ---------------------------------------------------------------------------

@Serializable
data class TicketListResponse(
    val success: Boolean,
    val tickets: List<Ticket>,
    val count: Int,
)

@Serializable
data class Ticket(
    val id: Int,
    val subject: String = "",
    val message: String = "",
    val priority: String = "",
    val status: String = "",
    @SerialName("tenant_name") val tenantName: String = "",
    @SerialName("tenant_email") val tenantEmail: String = "",
    @SerialName("license_key") val licenseKey: String? = null,
    @SerialName("created_at") val createdAt: String = "",
    @SerialName("updated_at") val updatedAt: String = "",
    val screenshots: List<String> = emptyList(),
    val replies: List<TicketReply> = emptyList(),
)

@Serializable
data class TicketReply(
    val id: Int,
    @SerialName("is_staff") val isStaff: Int = 0,
    @SerialName("author_name") val authorName: String? = null,
    val message: String = "",
    @SerialName("created_at") val createdAt: String = "",
    val screenshots: List<String> = emptyList(),
)

@Serializable
data class TicketResponse(val success: Boolean, val ticket: Ticket)

@Serializable
data class UpdateTicketRequest(
    val status: String? = null,
    val priority: String? = null,
)

@Serializable
data class ReplyTicketRequest(
    val message: String,
    val screenshots: List<String> = emptyList(),
)

// ---------------------------------------------------------------------------
// GEO Readiness scan
// ---------------------------------------------------------------------------

@Serializable
data class RunGeoScanRequest(
    val url: String,
    val keywords: List<String>,
    val language: String = "nl",
)

@Serializable
data class GeoScanResponse(
    val success: Boolean,
    @SerialName("scan_id") val scanId: Int,
    val report: GeoScanReport,
)

@Serializable
data class RecentGeoScansResponse(
    val success: Boolean,
    val scans: List<GeoScanSummary>,
    val count: Int,
)

@Serializable
data class GeoScanSummary(
    val id: Int,
    val url: String,
    val keywords: String = "",
    val language: String = "nl",
    val status: String = "",
    val score: Int? = null,
    @SerialName("created_at") val createdAt: String = "",
    val result: GeoScanReport? = null,
)

@Serializable
data class GeoScanDetailResponse(
    val success: Boolean,
    val scan: GeoScanSummary,
)

@Serializable
data class GeoScanReport(
    val url: String = "",
    val keywords: List<String> = emptyList(),
    val language: String = "nl",
    @SerialName("scanned_at") val scannedAt: String = "",
    val score: Int = 0,
    @Serializable(with = FlexibleIntMapSerializer::class)
    val breakdown: Map<String, Int> = emptyMap(),
    val findings: List<String> = emptyList(),
    val recommendations: List<String> = emptyList(),
    @SerialName("priority_ranked_recommendations") val priorityRankedRecommendations: List<String> = emptyList(),
    val strengths: List<String> = emptyList(),
    val weaknesses: List<String> = emptyList(),
    val readability: Int = 0,
    val eeat: Int = 0,
    @SerialName("content_freshness") val contentFreshness: Int = 0,
    @SerialName("mobile_friendly") val mobileFriendly: Int = 0,
    @SerialName("internal_linking") val internalLinking: Int = 0,
    @SerialName("page_metadata") val pageMetadata: Int = 0,
    @SerialName("entity_coverage") val entityCoverage: Int = 0,
    @SerialName("competitive_gap") val competitiveGap: Int = 0,
    @SerialName("keywords_analysis") val keywordsAnalysis: List<KeywordAnalysis> = emptyList(),
    @SerialName("page_text_preview") val pageTextPreview: String = "",
    @SerialName("html_source") val htmlSource: String = "",
)

@Serializable
data class KeywordAnalysis(
    val keyword: String,
    @SerialName("has_ai_overview") val hasAiOverview: Boolean = false,
    val ai_text: String = "",
    @SerialName("ai_sources_count") val aiSourcesCount: Int = 0,
    @SerialName("target_cited") val targetCited: Boolean = false,
    @SerialName("competitor_citations") val competitorCitations: List<CompetitorCitation> = emptyList(),
)

@Serializable
data class CompetitorCitation(
    val host: String = "",
    val title: String = "",
    val url: String = "",
)

// ---------------------------------------------------------------------------
// AI models
// ---------------------------------------------------------------------------

@Serializable
data class AiModelsResponse(
    val success: Boolean,
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("use_cases") val useCases: Map<String, String> = emptyMap(),
    val standard: ModelTier,
    val premium: ModelTier,
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("all_models") val allModels: Map<String, String> = emptyMap(),
    @SerialName("all_model_count") val allModelCount: Int = 0,
)

@Serializable
data class ModelTier(
    @Serializable(with = FlexibleStringMapSerializer::class)
    val routing: Map<String, String> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    val defaults: Map<String, String> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    val models: Map<String, String> = emptyMap(),
)

@Serializable
data class SaveAiModelsRequest(
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("standard_routing") val standardRouting: Map<String, String>? = null,
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("premium_routing") val premiumRouting: Map<String, String>? = null,
)

@Serializable
data class SaveAiModelsResponse(
    val success: Boolean,
    @Serializable(with = FlexibleNestedStringMapSerializer::class)
    val saved: Map<String, Map<String, String>> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    val standard: Map<String, String> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    val premium: Map<String, String> = emptyMap(),
)

@Serializable
data class RefreshAiModelsResponse(
    val success: Boolean,
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("all_models") val allModels: Map<String, String> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("standard_models") val standardModels: Map<String, String> = emptyMap(),
    @Serializable(with = FlexibleStringMapSerializer::class)
    @SerialName("premium_models") val premiumModels: Map<String, String> = emptyMap(),
    @SerialName("all_model_count") val allModelCount: Int = 0,
)
