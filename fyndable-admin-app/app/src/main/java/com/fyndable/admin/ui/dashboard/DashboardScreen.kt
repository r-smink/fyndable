package com.fyndable.admin.ui.dashboard

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.fyndable.admin.data.remote.LicenseStats
import com.fyndable.admin.data.remote.RevenueStats
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.data.repo.UsageRepository
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.SectionHeader
import com.fyndable.admin.ui.components.StatCard
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class DashboardData(
    val licenseStats: LicenseStats,
    val revenue: RevenueStats,
)

sealed class DashboardState {
    data object Loading : DashboardState()
    data class Success(val data: DashboardData) : DashboardState()
    data class Error(val message: String) : DashboardState()
}

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val licenseRepository: LicenseRepository,
    private val usageRepository: UsageRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<DashboardState>(DashboardState.Loading)
    val state = _state.asStateFlow()

    fun load() {
        _state.value = DashboardState.Loading
        viewModelScope.launch {
            val statsResult = licenseRepository.getStats()
            val revenueResult = usageRepository.revenue()
            if (statsResult is ApiResult.Success && revenueResult is ApiResult.Success) {
                _state.value = DashboardState.Success(
                    DashboardData(statsResult.data.stats, revenueResult.data.stats)
                )
            } else {
                val msg = when {
                    statsResult is ApiResult.Error -> statsResult.message
                    revenueResult is ApiResult.Error -> revenueResult.message
                    else -> "Unknown error"
                }
                _state.value = DashboardState.Error(msg)
            }
        }
    }
}

@Composable
fun DashboardScreen(
    onNavigate: (String) -> Unit,
    viewModel: DashboardViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) {
        if (state is DashboardState.Loading) viewModel.load()
    }

    when (val s = state) {
        is DashboardState.Loading -> LoadingIndicator()
        is DashboardState.Error -> ErrorView(s.message, onRetry = { viewModel.load() })
        is DashboardState.Success -> DashboardContent(s.data, onNavigate)
    }
}

@Composable
private fun DashboardContent(data: DashboardData, onNavigate: (String) -> Unit) {
    val stats = data.licenseStats
    val rev = data.revenue

    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
    ) {
        SectionHeader("Overview")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            StatCard(
                title = "Total Licenses",
                value = stats.total.toString(),
                modifier = Modifier.weight(1f),
            )
            StatCard(
                title = "Active Tenants",
                value = rev.activeTenants.toString(),
                modifier = Modifier.weight(1f),
            )
        }
        Spacer(Modifier.height(12.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            StatCard(
                title = "MRR",
                value = "€${"%,.0f".format(rev.mrr)}",
                modifier = Modifier.weight(1f),
            )
            StatCard(
                title = "ARR",
                value = "€${"%,.0f".format(rev.arr)}",
                modifier = Modifier.weight(1f),
            )
        }
        Spacer(Modifier.height(12.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            StatCard(
                title = "Created Today",
                value = stats.createdToday.toString(),
                modifier = Modifier.weight(1f),
            )
            StatCard(
                title = "Paid Tenants",
                value = rev.paidTenants.toString(),
                modifier = Modifier.weight(1f),
            )
        }

        Spacer(Modifier.height(24.dp))
        SectionHeader("Revenue by Tier")

        rev.revenueByTier.forEach { (tier, info) ->
            Card(
                modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(1.dp),
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Column {
                        Text(tier.replaceFirstChar { it.uppercase() }, fontWeight = FontWeight.SemiBold)
                        Text("${info.tenants} tenants", style = MaterialTheme.typography.bodyMedium)
                    }
                    Text(
                        "€${"%,.0f".format(info.revenue)}",
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }

        Spacer(Modifier.height(24.dp))
        SectionHeader("Licenses by Status")
        stats.byStatus.forEach { row ->
            Row(
                modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(row.status.replaceFirstChar { it.uppercase() })
                Text(row.count.toString(), fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
