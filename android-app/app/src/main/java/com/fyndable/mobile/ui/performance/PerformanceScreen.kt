package com.fyndable.mobile.ui.performance

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
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import com.fyndable.mobile.data.model.RankKeyword
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

@Composable
fun PerformanceScreen(
    authStore: AuthStore,
    viewModel: PerformanceViewModel = viewModel(factory = ScreenViewModelFactory(authStore))
) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    val toast by viewModel.toast.collectAsStateWithLifecycle()
    val isChecking by viewModel.isChecking.collectAsStateWithLifecycle()
    val snackbarHostState = remember { SnackbarHostState() }
    var selectedTab by remember { mutableStateOf(0) }

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
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            TabRow(selectedTabIndex = selectedTab) {
                Tab(
                    selected = selectedTab == 0,
                    onClick = { selectedTab = 0 },
                    text = { Text("Rankings") }
                )
                Tab(
                    selected = selectedTab == 1,
                    onClick = { selectedTab = 1 },
                    text = { Text("Post scores") }
                )
            }

            when (selectedTab) {
                0 -> when (val s = state) {
                    is PerformanceViewModel.UiState.Loading -> LoadingState()
                    is PerformanceViewModel.UiState.Error -> ErrorState(s.message)
                    is PerformanceViewModel.UiState.Success -> {
                        if (s.ranks.isEmpty()) {
                            EmptyState(
                                icon = Icons.Filled.BarChart,
                                message = "Nog geen rank tracking keywords."
                            )
                        } else {
                            // Stats summary
                            val top3 = s.ranks.count { (it.position ?: it.rank ?: 999) <= 3 }
                            val top10 = s.ranks.count { (it.position ?: it.rank ?: 999) <= 10 }
                            val top100 = s.ranks.count { (it.position ?: it.rank ?: 999) <= 100 }

                            Column {
                                StatsGrid(top3, top10, top100, s.ranks.size)
                                LazyColumn(
                                    modifier = Modifier.fillMaxSize(),
                                    contentPadding = PaddingValues(16.dp),
                                    verticalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    items(s.ranks) { rank ->
                                        RankCard(rank = rank, onCheck = {
                                            viewModel.checkRank(rank.keyword ?: rank.keywordName ?: "")
                                        })
                                    }
                                }
                            }
                        }
                    }
                }
                1 -> {
                    // Post scores tab - placeholder for now
                    EmptyState(
                        icon = Icons.Filled.BarChart,
                        message = "Post scores binnenkort beschikbaar."
                    )
                }
            }
        }
    }

    if (isChecking) {
        LoadingState(message = "Ranking check gestart…")
    }
}

@Composable
private fun StatsGrid(top3: Int, top10: Int, top100: Int, total: Int) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        StatCard("Top 3", top3, Modifier.weight(1f), SuccessGreen)
        StatCard("Top 10", top10, Modifier.weight(1f), WarningAmber)
        StatCard("Top 100", top100, Modifier.weight(1f), DangerRed)
        StatCard("Totaal", total, Modifier.weight(1f), FyndableBlue)
    }
}

@Composable
private fun StatCard(label: String, value: Int, modifier: Modifier = Modifier, color: androidx.compose.ui.graphics.Color) {
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
private fun RankCard(rank: RankKeyword, onCheck: () -> Unit) {
    val pos = rank.position ?: rank.rank ?: 0
    val posColor = if (pos <= 3) SuccessGreen
        else if (pos <= 10) WarningAmber
        else if (pos <= 100) DangerRed
        else FyndableBlue

    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = rank.keyword ?: rank.keywordName ?: "",
                    style = MaterialTheme.typography.titleMedium
                )
                Spacer(modifier = Modifier.height(6.dp))
                if (pos > 0) {
                    StatusBadge("#$pos", posColor)
                } else {
                    StatusBadge("-", FyndableBlue)
                }
            }
            TextButton(onClick = onCheck) { Text("Check") }
        }
    }
}
