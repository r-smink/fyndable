package com.fyndable.admin.ui.dashboard

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.LicenseStats
import com.fyndable.admin.data.remote.RevenueStats
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.data.repo.UsageRepository
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.OpsHeader
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

    Scaffold(
        topBar = { OpsHeader(title = "Dashboard") },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        when (val s = state) {
            is DashboardState.Loading -> LoadingIndicator(Modifier.padding(padding))
            is DashboardState.Error -> ErrorView(s.message, onRetry = { viewModel.load() }, Modifier.padding(padding))
            is DashboardState.Success -> DashboardContent(s.data, onNavigate, Modifier.padding(padding))
        }
    }
}

@Composable
private fun DashboardContent(data: DashboardData, onNavigate: (String) -> Unit, modifier: Modifier = Modifier) {
    val stats = data.licenseStats
    val rev = data.revenue

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
    ) {
        SectionHeader("Key Metrics")

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp),
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
        Spacer(Modifier.height(16.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            StatCard(
                title = "Monthly Revenue",
                value = "€${"%,.0f".format(rev.mrr)}",
                modifier = Modifier.weight(1f),
            )
            StatCard(
                title = "Annual Run Rate",
                value = "€${"%,.0f".format(rev.arr)}",
                modifier = Modifier.weight(1f),
            )
        }

        Spacer(Modifier.height(32.dp))
        SectionHeader("Revenue by Subscription Tier")

        rev.revenueByTier.forEach { (tier, info) ->
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 6.dp),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = Color.White),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                border = BorderStroke(1.dp, Color(0xFFF1F5F9))
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = tier.replaceFirstChar { it.uppercase() },
                            fontWeight = FontWeight.Bold,
                            fontSize = 16.sp,
                            color = Color(0xFF1E293B)
                        )
                        Text(
                            text = "${info.tenants} active tenants",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Color(0xFF64748B)
                        )
                    }
                    Text(
                        text = "€${"%,.0f".format(info.revenue)}",
                        fontWeight = FontWeight.Black,
                        fontSize = 18.sp,
                        color = Color(0xFF7C3AED),
                    )
                }
            }
        }

        Spacer(Modifier.height(32.dp))
        SectionHeader("Licenses by Status")
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
            border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFF1F5F9))
        ) {
            Column(Modifier.padding(20.dp)) {
                stats.byStatus.forEachIndexed { index, row ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                    ) {
                        Text(
                            text = row.status.replaceFirstChar { it.uppercase() },
                            color = Color(0xFF475569),
                            fontWeight = FontWeight.Medium
                        )
                        Text(
                            text = row.count.toString(),
                            fontWeight = FontWeight.Bold,
                            color = Color(0xFF1E293B)
                        )
                    }
                    if (index < stats.byStatus.size - 1) {
                        HorizontalDivider(
                            modifier = Modifier.padding(vertical = 12.dp),
                            color = Color(0xFFF1F5F9)
                        )
                    }
                }
            }
        }
        Spacer(Modifier.height(32.dp))
    }
}
