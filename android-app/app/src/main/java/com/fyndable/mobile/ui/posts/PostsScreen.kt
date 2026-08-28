package com.fyndable.mobile.ui.posts

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.background
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.graphics.Color
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Article
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.fyndable.mobile.data.model.CreatedPost
import com.fyndable.mobile.data.model.PostMetrics
import com.fyndable.mobile.data.model.PostStats
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.ScreenViewModelFactory
import com.fyndable.mobile.ui.components.EmptyState
import com.fyndable.mobile.ui.components.ErrorState
import com.fyndable.mobile.ui.components.LoadingState
import com.fyndable.mobile.ui.components.StatusBadge
import com.fyndable.mobile.ui.theme.FyndableBlue
import com.fyndable.mobile.ui.theme.FyndableInk
import com.fyndable.mobile.ui.theme.FyndablePurple
import com.fyndable.mobile.ui.theme.Gray500
import com.fyndable.mobile.ui.theme.InfoBlue
import com.fyndable.mobile.ui.theme.SuccessGreen
import com.fyndable.mobile.ui.theme.WarningAmber

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostsScreen(
    authStore: AuthStore,
    viewModel: PostsViewModel = viewModel(factory = ScreenViewModelFactory(authStore))
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val selectedPost by viewModel.selectedPost.collectAsStateWithLifecycle()
    val selectedMetrics by viewModel.selectedMetrics.collectAsStateWithLifecycle()
    val toast by viewModel.toast.collectAsStateWithLifecycle()
    val isLoading by viewModel.isLoading.collectAsStateWithLifecycle()
    val snackbarHostState = remember { SnackbarHostState() }
    var showScheduleSheet by rememberSaveable { mutableStateOf(false) }
    var schedulePostId by remember { mutableStateOf<Int?>(null) }

    LaunchedEffect(toast) {
        toast?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearToast()
        }
    }

    Scaffold(
        containerColor = androidx.compose.ui.graphics.Color.Transparent,
        snackbarHost = { SnackbarHost(snackbarHostState) }
    ) { padding ->
        when (val s = state) {
            is PostsViewModel.UiState.Loading -> LoadingState(modifier = Modifier.padding(padding))
            is PostsViewModel.UiState.Error -> ErrorState(s.message, modifier = Modifier.padding(padding))
            is PostsViewModel.UiState.Success -> {
                Column(modifier = Modifier.fillMaxSize().padding(padding)) {
                    // Stats
                    s.stats?.let { stats -> StatsRow(stats) }

                    if (s.posts.isEmpty()) {
                        EmptyState(
                            icon = Icons.Filled.Article,
                            message = "Nog geen AI-gegenereerde berichten.",
                            modifier = Modifier.fillMaxSize()
                        )
                    } else {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            items(s.posts) { post ->
                                PostCard(post = post, onClick = { viewModel.selectPost(post) })
                            }
                        }
                    }
                }
            }
        }
    }

    // Post detail sheet
    selectedPost?.let { post ->
        val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
        val status = post.postStatus ?: post.status ?: ""
        val id = post.ID ?: post.id ?: 0

        ModalBottomSheet(
            onDismissRequest = { viewModel.clearSelectedPost() },
            sheetState = sheetState
        ) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text(
                    text = post.postTitle ?: post.title ?: "Onbekend",
                    style = MaterialTheme.typography.titleLarge
                )
                Spacer(modifier = Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatusBadge(
                        status,
                        if (status == "publish") SuccessGreen
                        else if (status == "future") WarningAmber
                        else FyndableBlue
                    )
                    (post.postDate ?: post.date)?.let {
                        StatusBadge(it.take(10), FyndableBlue)
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))

                val content = post.postContent ?: post.content ?: ""
                if (content.isNotEmpty()) {
                    Text(
                        text = content.take(2000) + if (content.length > 2000) "…" else "",
                        style = MaterialTheme.typography.bodySmall
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                }

                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (status == "draft") {
                        TextButton(onClick = {
                            schedulePostId = id
                            showScheduleSheet = true
                        }) { Text("Inplannen") }
                        TextButton(onClick = { viewModel.publishPost(id) }) { Text("Publiceren") }
                    }
                    if (status == "publish") {
                        TextButton(onClick = { viewModel.unpublishPost(id) }) { Text("Depubliceren") }
                    }
                    TextButton(onClick = { viewModel.deletePost(id) }) { Text("Verwijderen") }
                }
                Spacer(modifier = Modifier.height(16.dp))
                MetricsSection(metrics = selectedMetrics)
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }

    // Schedule sheet
    if (showScheduleSheet && schedulePostId != null) {
        val sheetState = rememberModalBottomSheetState()
        var date by remember { mutableStateOf("") }

        ModalBottomSheet(
            onDismissRequest = { showScheduleSheet = false; schedulePostId = null },
            sheetState = sheetState
        ) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text("Bericht inplannen", style = MaterialTheme.typography.titleLarge)
                Spacer(modifier = Modifier.height(16.dp))
                OutlinedTextField(
                    value = date,
                    onValueChange = { date = it },
                    label = { Text("Publicatiedatum (YYYY-MM-DD HH:MM)") },
                    placeholder = { Text("2025-01-15 10:00") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
                Spacer(modifier = Modifier.height(20.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    TextButton(onClick = {
                        schedulePostId?.let { viewModel.schedulePost(it, date) }
                        showScheduleSheet = false
                        schedulePostId = null
                    }) { Text("Inplannen") }
                    TextButton(onClick = {
                        showScheduleSheet = false
                        schedulePostId = null
                    }) { Text("Annuleren") }
                }
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }

    if (isLoading) {
        LoadingState(message = "Bezig…")
    }
}

@Composable
private fun StatsRow(stats: PostStats) {
    Column(
        modifier = Modifier.fillMaxWidth().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StatCard("Totaal", stats.total, Modifier.weight(1f), FyndablePurple)
            StatCard("Gepubliceerd", stats.published, Modifier.weight(1f), SuccessGreen)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StatCard("Gepland", stats.scheduled + stats.future, Modifier.weight(1f), WarningAmber)
            StatCard("Concept", stats.draft, Modifier.weight(1f), InfoBlue)
        }
    }
}

@Composable
private fun StatCard(label: String, value: Int, modifier: Modifier = Modifier, color: Color) {
    Card(
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        modifier = modifier
    ) {
        Column {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(4.dp)
                    .background(color)
            )
            Column(modifier = Modifier.padding(12.dp)) {
                Text(
                    "$value",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = color
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    label,
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = Gray500,
                    letterSpacing = 0.05.sp
                )
            }
        }
    }
}

@Composable
private fun PostCard(post: CreatedPost, onClick: () -> Unit) {
    val status = post.postStatus ?: post.status ?: ""
    val statusColor = if (status == "publish") SuccessGreen
        else if (status == "future") WarningAmber
        else FyndableBlue

    Card(
        onClick = onClick,
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = post.postTitle ?: post.title ?: "Onbekend",
                style = MaterialTheme.typography.titleMedium,
                color = FyndableInk
            )
            Spacer(modifier = Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatusBadge(status, statusColor)
                (post.postDate ?: post.date)?.let {
                    StatusBadge(it.take(10), FyndableBlue)
                }
            }
        }
    }
}

@Composable
private fun MetricsSection(metrics: PostMetrics?) {
    when {
        metrics == null -> {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center
            ) {
                CircularProgressIndicator(modifier = Modifier.padding(8.dp), strokeWidth = 2.dp)
            }
        }
        metrics.success == false || metrics.connected == false -> {
            Text(
                text = metrics.message ?: "Geen Search Console data beschikbaar.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
        else -> {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = "Zoekprestaties (laatste 28 dagen)",
                    style = MaterialTheme.typography.labelLarge
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    MetricCard(
                        label = "Indrukken",
                        value = (metrics.impressions ?: 0).toString(),
                        modifier = Modifier.weight(1f)
                    )
                    MetricCard(
                        label = "Kliks",
                        value = (metrics.clicks ?: 0).toString(),
                        modifier = Modifier.weight(1f)
                    )
                    MetricCard(
                        label = "CTR",
                        value = "${metrics.ctr ?: 0.0}%",
                        modifier = Modifier.weight(1f)
                    )
                    MetricCard(
                        label = "Positie",
                        value = metrics.position?.let { "%.1f".format(it) } ?: "-",
                        modifier = Modifier.weight(1f)
                    )
                }
            }
        }
    }
}

@Composable
private fun MetricCard(label: String, value: String, modifier: Modifier = Modifier) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = modifier
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally
        ) {
            Text(value, style = MaterialTheme.typography.titleMedium, color = FyndableBlue)
            Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
