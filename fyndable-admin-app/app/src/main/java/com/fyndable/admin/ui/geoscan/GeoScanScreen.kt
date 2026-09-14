package com.fyndable.admin.ui.geoscan

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.GeoScanReport
import com.fyndable.admin.data.remote.GeoScanSummary
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.GeoScanRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.SectionHeader
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class RecentScansState {
    data object Loading : RecentScansState()
    data class Success(val scans: List<GeoScanSummary>) : RecentScansState()
    data class Error(val message: String) : RecentScansState()
}

@HiltViewModel
class GeoScanViewModel @Inject constructor(
    private val repository: GeoScanRepository,
) : ViewModel() {

    private val _recentState = MutableStateFlow<RecentScansState>(RecentScansState.Loading)
    val recentState = _recentState.asStateFlow()

    var scanning by mutableStateOf(false)
        private set
    var scanError by mutableStateOf<String?>(null)
        private set

    fun loadRecent() {
        _recentState.value = RecentScansState.Loading
        viewModelScope.launch {
            when (val result = repository.recent(30)) {
                is ApiResult.Success -> _recentState.value = RecentScansState.Success(result.data.scans)
                is ApiResult.Error -> _recentState.value = RecentScansState.Error(result.message)
            }
        }
    }

    fun runScan(url: String, keywords: List<String>, language: String, onDone: (GeoScanReport?) -> Unit) {
        scanning = true
        scanError = null
        viewModelScope.launch {
            when (val result = repository.run(url, keywords, language)) {
                is ApiResult.Success -> {
                    scanning = false
                    loadRecent()
                    onDone(result.data.report)
                }
                is ApiResult.Error -> {
                    scanning = false
                    scanError = result.message
                    onDone(null)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GeoScanScreen(viewModel: GeoScanViewModel = hiltViewModel()) {
    val recentState by viewModel.recentState.collectAsStateWithLifecycle()
    var url by remember { mutableStateOf("https://") }
    var keywords by remember { mutableStateOf("") }
    var language by remember { mutableStateOf("nl") }
    var reportDialog by remember { mutableStateOf<GeoScanReport?>(null) }

    LaunchedEffect(Unit) {
        if (recentState is RecentScansState.Loading) viewModel.loadRecent()
    }

    Scaffold(topBar = { TopAppBar(title = { Text("GEO Readiness Scan") }) }) { padding ->
        Column(
            modifier = Modifier.fillMaxSize().padding(padding).padding(16.dp).verticalScroll(rememberScrollState()),
        ) {
            SectionHeader("New Scan")

            OutlinedTextField(
                value = url,
                onValueChange = { url = it },
                label = { Text("Prospect URL") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = keywords,
                onValueChange = { keywords = it },
                label = { Text("Keywords (one per line, max 10)") },
                modifier = Modifier.fillMaxWidth().height(120.dp),
            )
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("nl" to "Dutch", "en" to "English").forEach { (code, label) ->
                    TextButton(onClick = { language = code }) {
                        Text(if (language == code) "[$label]" else label)
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            if (viewModel.scanning) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(4.dp))
                Text("Scanning… this can take 30-90 seconds", style = MaterialTheme.typography.bodyMedium)
            } else {
                Button(
                    onClick = {
                        val kwList = keywords.lines().map { it.trim() }.filter { it.isNotBlank() }
                        if (url.isNotBlank() && kwList.isNotEmpty()) {
                            viewModel.runScan(url, kwList, language) { report ->
                                if (report != null) reportDialog = report
                            }
                        }
                    },
                    enabled = url.isNotBlank() && keywords.lines().any { it.isNotBlank() },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Start Scan") }
            }
            viewModel.scanError?.let {
                Spacer(Modifier.height(8.dp))
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
            }

            Spacer(Modifier.height(24.dp))
            SectionHeader("Recent Scans")

            when (val s = recentState) {
                is RecentScansState.Loading -> LoadingIndicator()
                is RecentScansState.Error -> ErrorView(s.message, onRetry = { viewModel.loadRecent() })
                is RecentScansState.Success -> {
                    if (s.scans.isEmpty()) {
                        EmptyState("No scans yet")
                    } else {
                        s.scans.forEach { scan -> ScanCard(scan) { reportDialog = scan.result } }
                    }
                }
            }
        }
    }

    reportDialog?.let { report ->
        ReportDialog(report = report, onDismiss = { reportDialog = null })
    }
}

@Composable
private fun ScanCard(scan: GeoScanSummary, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(1.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(scan.url, fontWeight = FontWeight.SemiBold, maxLines = 1)
                Text("${scan.score ?: 0}/100", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
            }
            Text(scan.keywords, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(scan.createdAt, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onClick) { Text("View Report") }
        }
    }
}

@Composable
private fun ReportDialog(report: GeoScanReport, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("GEO Report — ${report.score}/100") },
        text = {
            Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                Text("URL: ${report.url}", style = MaterialTheme.typography.labelMedium)
                Text("Scanned: ${report.scannedAt}", style = MaterialTheme.typography.labelMedium)
                Spacer(Modifier.height(12.dp))

                SectionHeader("Score Breakdown")
                report.breakdown.forEach { (key, value) ->
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(key.replace("_", " ").replaceFirstChar { it.uppercase() })
                        Text("$value", fontWeight = FontWeight.SemiBold)
                    }
                }

                if (report.strengths.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    SectionHeader("Strengths")
                    report.strengths.forEach { Text("• $it") }
                }
                if (report.weaknesses.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    SectionHeader("Weaknesses")
                    report.weaknesses.forEach { Text("• $it") }
                }
                if (report.priorityRankedRecommendations.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    SectionHeader("Priority Recommendations")
                    report.priorityRankedRecommendations.forEachIndexed { i, rec ->
                        Text("${i + 1}. $rec")
                    }
                }
                if (report.findings.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    SectionHeader("Findings")
                    report.findings.forEach { Text("• $it") }
                }
                if (report.keywordsAnalysis.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    SectionHeader("Keyword Analysis")
                    report.keywordsAnalysis.forEach { ka ->
                        Text("• ${ka.keyword}", fontWeight = FontWeight.SemiBold)
                        Text("  AI Overview: ${if (ka.hasAiOverview) "Yes" else "No"} · Cited: ${if (ka.targetCited) "Yes" else "No"}")
                    }
                }
            }
        },
        confirmButton = { TextButton(onClick = onDismiss) { Text("Close") } },
    )
}
