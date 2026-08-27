package com.fyndable.mobile.ui.clusters

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
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Hub
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
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
import com.fyndable.mobile.data.model.Cluster
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.ScreenViewModelFactory
import com.fyndable.mobile.ui.components.EmptyState
import com.fyndable.mobile.ui.components.ErrorState
import com.fyndable.mobile.ui.components.LoadingState
import com.fyndable.mobile.ui.components.StatusBadge
import com.fyndable.mobile.ui.theme.FyndableBlue
import com.fyndable.mobile.ui.theme.FyndableMagenta

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClustersScreen(
    authStore: AuthStore,
    viewModel: ClustersViewModel = viewModel(factory = ScreenViewModelFactory(authStore))
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val selectedCluster by viewModel.selectedCluster.collectAsStateWithLifecycle()
    val isGenerating by viewModel.isGenerating.collectAsStateWithLifecycle()
    val contentResult by viewModel.contentResult.collectAsStateWithLifecycle()
    val toast by viewModel.toast.collectAsStateWithLifecycle()
    val snackbarHostState = remember { SnackbarHostState() }
    var showAddSheet by rememberSaveable { mutableStateOf(false) }

    LaunchedEffect(toast) {
        toast?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.clearToast()
        }
    }

    Scaffold(
        containerColor = androidx.compose.ui.graphics.Color.Transparent,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = { showAddSheet = true },
                icon = { Icon(Icons.Filled.Add, contentDescription = "Toevoegen") },
                text = { Text("Cluster") }
            )
        }
    ) { padding ->
        when (val s = state) {
            is ClustersViewModel.UiState.Loading -> LoadingState(modifier = Modifier.padding(padding))
            is ClustersViewModel.UiState.Error -> ErrorState(s.message, modifier = Modifier.padding(padding))
            is ClustersViewModel.UiState.Success -> {
                if (s.clusters.isEmpty()) {
                    EmptyState(
                        icon = Icons.Filled.Hub,
                        message = "Nog geen topic clusters. Tik op + om een cluster te genereren.",
                        modifier = Modifier.padding(padding)
                    )
                } else {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(s.clusters) { cluster ->
                            ClusterCard(cluster = cluster, onClick = { viewModel.selectCluster(cluster) })
                        }
                    }
                }
            }
        }
    }

    // Cluster detail sheet
    selectedCluster?.let { cluster ->
        val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
        ModalBottomSheet(
            onDismissRequest = { viewModel.clearSelectedCluster() },
            sheetState = sheetState
        ) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text(
                    text = cluster.pillarTopic ?: cluster.title ?: "Cluster",
                    style = MaterialTheme.typography.titleLarge
                )
                cluster.description?.let {
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(it, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Spacer(modifier = Modifier.height(16.dp))

                if (cluster.items.isEmpty()) {
                    Text("Geen items in dit cluster.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                } else {
                    Text("Cluster items:", style = MaterialTheme.typography.titleMedium)
                    Spacer(modifier = Modifier.height(8.dp))
                    LazyColumn(
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.height(400.dp)
                    ) {
                        items(cluster.items) { item ->
                            ClusterItemRow(
                                item = item,
                                onGenerate = {
                                    viewModel.generateClusterContent(
                                        cluster.id,
                                        item.title ?: item.keyword ?: "",
                                        item.keyword ?: ""
                                    )
                                }
                            )
                        }
                    }
                }
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }

    // Content result sheet
    contentResult?.let { result ->
        val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
        ModalBottomSheet(
            onDismissRequest = { viewModel.clearContentResult() },
            sheetState = sheetState
        ) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text("Gegenereerde content", style = MaterialTheme.typography.titleLarge)
                result.postId?.let {
                    Spacer(modifier = Modifier.height(8.dp))
                    StatusBadge("✓ Opgeslagen als concept (ID: $it)", FyndableBlue)
                }
                Spacer(modifier = Modifier.height(16.dp))
                val content = result.content ?: result.article ?: result.html ?: ""
                Text(
                    text = content,
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.height(300.dp)
                )
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }

    // Generate cluster sheet
    if (showAddSheet) {
        val sheetState = rememberModalBottomSheetState()
        var topic by remember { mutableStateOf("") }
        var countExpanded by remember { mutableStateOf(false) }
        var count by remember { mutableStateOf("10") }
        var langExpanded by remember { mutableStateOf(false) }
        var lang by remember { mutableStateOf("nl") }

        ModalBottomSheet(
            onDismissRequest = { showAddSheet = false },
            sheetState = sheetState
        ) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text("Topic Cluster genereren", style = MaterialTheme.typography.titleLarge)
                Spacer(modifier = Modifier.height(16.dp))
                OutlinedTextField(
                    value = topic,
                    onValueChange = { topic = it },
                    label = { Text("Pillar onderwerp") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
                Spacer(modifier = Modifier.height(12.dp))

                ExposedDropdownMenuBox(
                    expanded = countExpanded,
                    onExpandedChange = { countExpanded = !countExpanded }
                ) {
                    OutlinedTextField(
                        value = "${count} items",
                        onValueChange = {},
                        readOnly = true,
                        label = { Text("Aantal items") },
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = countExpanded) },
                        modifier = Modifier.fillMaxWidth().menuAnchor()
                    )
                    ExposedDropdownMenu(expanded = countExpanded, onDismissRequest = { countExpanded = false }) {
                        listOf("5", "10", "15", "20").forEach { c ->
                            DropdownMenuItem(text = { Text("$c items") }, onClick = { count = c; countExpanded = false })
                        }
                    }
                }
                Spacer(modifier = Modifier.height(12.dp))

                ExposedDropdownMenuBox(
                    expanded = langExpanded,
                    onExpandedChange = { langExpanded = !langExpanded }
                ) {
                    OutlinedTextField(
                        value = if (lang == "nl") "Nederlands" else "Engels",
                        onValueChange = {},
                        readOnly = true,
                        label = { Text("Taal") },
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = langExpanded) },
                        modifier = Modifier.fillMaxWidth().menuAnchor()
                    )
                    ExposedDropdownMenu(expanded = langExpanded, onDismissRequest = { langExpanded = false }) {
                        DropdownMenuItem(text = { Text("Nederlands") }, onClick = { lang = "nl"; langExpanded = false })
                        DropdownMenuItem(text = { Text("Engels") }, onClick = { lang = "en"; langExpanded = false })
                    }
                }
                Spacer(modifier = Modifier.height(20.dp))
                Button(
                    onClick = {
                        if (topic.isNotBlank()) {
                            viewModel.generateCluster(topic, count.toIntOrNull() ?: 10, lang)
                            showAddSheet = false
                        }
                    },
                    modifier = Modifier.fillMaxWidth()
                ) { Text("Cluster genereren") }
                Spacer(modifier = Modifier.height(24.dp))
            }
        }
    }

    if (isGenerating) {
        LoadingState(message = "AI maakt een topic cluster aan…\nDit kan tot een minuut duren.")
    }
}

@Composable
private fun ClusterCard(cluster: Cluster, onClick: () -> Unit) {
    Card(
        onClick = onClick,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = cluster.pillarTopic ?: cluster.title ?: "Onbekend",
                style = MaterialTheme.typography.titleMedium
            )
            Spacer(modifier = Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                val count = cluster.items.size.takeIf { it > 0 } ?: cluster.itemCount ?: 0
                StatusBadge("$count items", FyndableMagenta)
                cluster.status?.let { StatusBadge(it, FyndableBlue) }
            }
        }
    }
}

@Composable
private fun ClusterItemRow(
    item: com.fyndable.mobile.data.model.ClusterItem,
    onGenerate: () -> Unit
) {
    val status = item.status ?: item.postStatus ?: ""
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(12.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = item.title ?: item.keyword ?: "",
                    style = MaterialTheme.typography.bodyMedium
                )
                Spacer(modifier = Modifier.height(4.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    (item.role ?: item.clusterRole)?.let { StatusBadge(it, FyndableBlue) }
                    if (status.isNotEmpty()) StatusBadge(status, FyndableMagenta)
                }
            }
            if (status != "published") {
                TextButton(onClick = onGenerate) { Text("Genereer") }
            }
        }
    }
}
