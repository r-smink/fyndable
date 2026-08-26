package com.fyndable.mobile.ui.posts

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
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
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.fyndable.mobile.data.model.CreatedPost
import com.fyndable.mobile.data.model.PostStats
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.ScreenViewModelFactory
import com.fyndable.mobile.ui.components.EmptyState
import com.fyndable.mobile.ui.components.ErrorState
import com.fyndable.mobile.ui.components.LoadingState
import com.fyndable.mobile.ui.components.StatusBadge
import com.fyndable.mobile.ui.theme.DangerRed
import com.fyndable.mobile.ui.theme.FyndableBlue
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
    Row(
        modifier = Modifier.fillMaxWidth().padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        StatCard("Totaal", stats.total, Modifier.weight(1f))
        StatCard("Gepubliceerd", stats.published, Modifier.weight(1f), SuccessGreen)
        StatCard("Gepland", stats.scheduled + stats.future, Modifier.weight(1f), WarningAmber)
        StatCard("Concept", stats.draft, Modifier.weight(1f), FyndableBlue)
    }
}

@Composable
private fun StatCard(label: String, value: Int, modifier: Modifier = Modifier, color: androidx.compose.ui.graphics.Color = androidx.compose.ui.graphics.Color.White) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = modifier
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text("$value", style = MaterialTheme.typography.titleLarge, color = color)
            Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = post.postTitle ?: post.title ?: "Onbekend",
                style = MaterialTheme.typography.titleMedium
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
