package com.fyndable.mobile.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

@Serializable
data class Keyword(
    val id: Int? = null,
    val keyword: String,
    @SerialName("search_volume") val searchVolume: Int? = null,
    val difficulty: Int? = null,
    @SerialName("cluster_name") val clusterName: String? = null,
)

@Serializable
data class KeywordsResponse(
    val keywords: List<Keyword> = emptyList(),
    val total: Int? = null,
)

@Serializable
data class GenerateKeywordsRequest(
    val topic: String,
    val count: Int = 20,
    val language: String = "nl",
)

@Serializable
data class AddKeywordRequest(
    val keyword: String,
    @SerialName("search_volume") val searchVolume: Int? = null,
    val difficulty: Int? = null,
)

@Serializable
data class Cluster(
    val id: Int,
    @SerialName("pillar_topic") val pillarTopic: String? = null,
    val title: String? = null,
    val description: String? = null,
    val status: String? = null,
    val items: List<ClusterItem> = emptyList(),
    @SerialName("item_count") val itemCount: Int? = null,
)

@Serializable
data class ClusterItem(
    val id: Int? = null,
    val title: String? = null,
    val keyword: String? = null,
    val role: String? = null,
    @SerialName("cluster_role") val clusterRole: String? = null,
    val status: String? = null,
    @SerialName("post_status") val postStatus: String? = null,
)

@Serializable
data class ClustersResponse(
    val clusters: List<Cluster> = emptyList(),
)

@Serializable
data class GenerateClusterRequest(
    val topic: String,
    val count: Int = 10,
    val language: String = "nl",
)

@Serializable
data class GenerateContentRequest(
    val title: String,
    val keyword: String,
    @SerialName("word_count") val wordCount: Int = 1500,
    @SerialName("cluster_id") val clusterId: Int? = null,
)

@Serializable
data class ContentResult(
    val content: String? = null,
    val article: String? = null,
    val html: String? = null,
    @SerialName("post_id") val postId: Int? = null,
    val id: Int? = null,
)

@Serializable
data class WriteArticleRequest(
    val keyword: String,
    val title: String? = null,
    val tone: String = "professional",
    @SerialName("word_count") val wordCount: Int = 1500,
    @SerialName("include_faq") val includeFaq: Boolean = true,
    @SerialName("create_draft") val createDraft: Boolean = true,
)

@Serializable
data class CreatedPost(
    val ID: Int? = null,
    val id: Int? = null,
    @SerialName("post_title") val postTitle: String? = null,
    val title: String? = null,
    @SerialName("post_status") val postStatus: String? = null,
    val status: String? = null,
    @SerialName("post_date") val postDate: String? = null,
    val date: String? = null,
    @SerialName("post_content") val postContent: String? = null,
    val content: String? = null,
)

@Serializable
data class CreatedPostsResponse(
    val posts: List<CreatedPost> = emptyList(),
    val items: List<CreatedPost> = emptyList(),
)

@Serializable
data class PostStats(
    val total: Int = 0,
    val published: Int = 0,
    val scheduled: Int = 0,
    val future: Int = 0,
    val draft: Int = 0,
)

@Serializable
data class PostStatsResponse(
    val stats: PostStats? = null,
)

@Serializable
data class UpdatePostRequest(
    @SerialName("post_status") val postStatus: String,
    @SerialName("post_date") val postDate: String? = null,
)

@Serializable
data class DeletePostRequest(
    @SerialName("post_ids") val postIds: List<Int>,
)

@Serializable
data class RankKeyword(
    val keyword: String? = null,
    @SerialName("keyword_name") val keywordName: String? = null,
    val position: Int? = null,
    val rank: Int? = null,
    val url: String? = null,
    @SerialName("post_url") val postUrl: String? = null,
)

@Serializable
data class RanksResponse(
    val keywords: List<RankKeyword> = emptyList(),
)

@Serializable
data class ApiError(
    val code: String? = null,
    val message: String? = null,
    val data: JsonElement? = null,
)

@Serializable
data class QrLoginPayload(
    val site: String,
    val user: String,
    val pass: String,
    val app: String? = null,
    val uuid: String? = null,
    val ts: Long? = null,
)

@Serializable
data class PostMetrics(
    val success: Boolean = true,
    val connected: Boolean? = true,
    val message: String? = null,
    val clicks: Int? = null,
    val impressions: Int? = null,
    val ctr: Double? = null,
    val position: Double? = null,
    val period: PostMetricsPeriod? = null,
)

@Serializable
data class PostMetricsPeriod(
    val start: String? = null,
    val end: String? = null,
    val days: Int? = null,
)
