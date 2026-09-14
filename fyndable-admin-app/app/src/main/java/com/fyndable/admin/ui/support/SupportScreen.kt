package com.fyndable.admin.ui.support

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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.ReplyTicketRequest
import com.fyndable.admin.data.remote.Ticket
import com.fyndable.admin.data.remote.UpdateTicketRequest
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.SupportRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.SectionHeader
import com.fyndable.admin.ui.components.StatusBadge
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class TicketListState {
    data object Loading : TicketListState()
    data class Success(val tickets: List<Ticket>) : TicketListState()
    data class Error(val message: String) : TicketListState()
}

@HiltViewModel
class SupportViewModel @Inject constructor(
    private val repository: SupportRepository,
) : ViewModel() {

    private val _listState = MutableStateFlow<TicketListState>(TicketListState.Loading)
    val listState = _listState.asStateFlow()

    private val _detailState = MutableStateFlow<Ticket?>(null)
    val detailState = _detailState.asStateFlow()

    fun loadList(status: String? = null, priority: String? = null, search: String? = null) {
        _listState.value = TicketListState.Loading
        viewModelScope.launch {
            when (val result = repository.list(status, priority, search)) {
                is ApiResult.Success -> _listState.value = TicketListState.Success(result.data.tickets)
                is ApiResult.Error -> _listState.value = TicketListState.Error(result.message)
            }
        }
    }

    fun loadDetail(id: Int) {
        viewModelScope.launch {
            when (val result = repository.get(id)) {
                is ApiResult.Success -> _detailState.value = result.data.ticket
                is ApiResult.Error -> _detailState.value = null
            }
        }
    }

    fun reply(id: Int, message: String, onDone: (Boolean) -> Unit) {
        viewModelScope.launch {
            when (val result = repository.reply(id, ReplyTicketRequest(message))) {
                is ApiResult.Success -> { _detailState.value = result.data.ticket; onDone(true) }
                is ApiResult.Error -> onDone(false)
            }
        }
    }

    fun updateTicket(id: Int, status: String?, priority: String?) {
        viewModelScope.launch {
            when (val result = repository.update(id, UpdateTicketRequest(status, priority))) {
                is ApiResult.Success -> _detailState.value = result.data.ticket
                is ApiResult.Error -> {}
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SupportScreen(viewModel: SupportViewModel = hiltViewModel()) {
    val listState by viewModel.listState.collectAsStateWithLifecycle()
    val detailTicket by viewModel.detailState.collectAsStateWithLifecycle()
    var selectedTicketId by remember { mutableStateOf<Int?>(null) }
    var search by remember { mutableStateOf("") }
    var statusFilter by remember { mutableStateOf("") }

    LaunchedEffect(Unit) {
        if (listState is TicketListState.Loading) viewModel.loadList()
    }

    if (selectedTicketId != null) {
        TicketDetailScreen(
            ticketId = selectedTicketId!!,
            ticket = detailTicket,
            viewModel = viewModel,
            onBack = {
                selectedTicketId = null
                viewModel.loadList()
            },
        )
        return
    }

    Scaffold(topBar = { TopAppBar(title = { Text("Support Tickets") }) }) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).padding(16.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = search,
                    onValueChange = { search = it },
                    label = { Text("Search") },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    singleLine = true,
                    modifier = Modifier.weight(1f),
                )
                OutlinedTextField(
                    value = statusFilter,
                    onValueChange = { statusFilter = it },
                    label = { Text("Status") },
                    singleLine = true,
                    modifier = Modifier.weight(1f),
                )
            }
            Spacer(Modifier.height(8.dp))
            TextButton(onClick = { viewModel.loadList(statusFilter.ifBlank { null }, null, search.ifBlank { null }) }) {
                Text("Apply Filters")
            }
            Spacer(Modifier.height(8.dp))

            when (val s = listState) {
                is TicketListState.Loading -> LoadingIndicator()
                is TicketListState.Error -> ErrorView(s.message, onRetry = { viewModel.loadList() })
                is TicketListState.Success -> {
                    if (s.tickets.isEmpty()) {
                        EmptyState("No tickets found")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.tickets) { ticket ->
                                TicketCard(ticket) {
                                    selectedTicketId = ticket.id
                                    viewModel.loadDetail(ticket.id)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun TicketCard(ticket: Ticket, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(1.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("#${ticket.id}", fontWeight = FontWeight.Bold)
                StatusBadge(ticket.status)
            }
            Text(ticket.subject, fontWeight = FontWeight.SemiBold)
            Text(ticket.tenantName, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text("Priority: ${ticket.priority}", style = MaterialTheme.typography.labelMedium)
            Text(ticket.updatedAt, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(4.dp))
            TextButton(onClick = onClick) { Text("View") }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TicketDetailScreen(
    ticketId: Int,
    ticket: Ticket?,
    viewModel: SupportViewModel,
    onBack: () -> Unit,
) {
    var replyText by remember { mutableStateOf("") }
    var showStatusDialog by remember { mutableStateOf(false) }

    Scaffold(topBar = {
        TopAppBar(
            title = { Text("Ticket #$ticketId") },
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                }
            },
        )
    }) { padding ->
        if (ticket == null) {
            Box(Modifier.padding(padding).fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
            return@Scaffold
        }

        Column(
            Modifier.fillMaxSize().padding(padding).padding(16.dp).verticalScroll(rememberScrollState()),
        ) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(ticket.subject, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                StatusBadge(ticket.status)
            }
            Text("${ticket.tenantName} (${ticket.tenantEmail})", style = MaterialTheme.typography.bodyMedium)
            Text("Created: ${ticket.createdAt}", style = MaterialTheme.typography.labelMedium)
            Spacer(Modifier.height(12.dp))

            SectionHeader("Original Message")
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
                Text(ticket.message, modifier = Modifier.padding(12.dp))
            }

            Spacer(Modifier.height(16.dp))
            SectionHeader("Conversation")
            ticket.replies.forEach { reply ->
                Card(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (reply.isStaff == 1) MaterialTheme.colorScheme.primaryContainer
                        else MaterialTheme.colorScheme.surface
                    ),
                ) {
                    Column(Modifier.padding(12.dp)) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text(
                                if (reply.isStaff == 1) (reply.authorName ?: "Support") else ticket.tenantName,
                                fontWeight = FontWeight.SemiBold,
                            )
                            Text(reply.createdAt, style = MaterialTheme.typography.labelMedium)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text(reply.message)
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
            SectionHeader("Reply")
            OutlinedTextField(
                value = replyText,
                onValueChange = { replyText = it },
                label = { Text("Message") },
                modifier = Modifier.fillMaxWidth().height(120.dp),
            )
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(
                    onClick = {
                        if (replyText.isNotBlank()) {
                            viewModel.reply(ticketId, replyText) { success ->
                                if (success) replyText = ""
                            }
                        }
                    },
                ) { Text("Send Reply") }
                TextButton(onClick = { showStatusDialog = true }) { Text("Update Status") }
            }
        }
    }

    if (showStatusDialog) {
        AlertDialog(
            onDismissRequest = { showStatusDialog = false },
            title = { Text("Update Status") },
            text = {
                Column {
                    listOf("open", "reaction", "closed").forEach { status ->
                        TextButton(
                            onClick = {
                                viewModel.updateTicket(ticketId, status, null)
                                showStatusDialog = false
                            },
                            modifier = Modifier.fillMaxWidth(),
                        ) { Text(status.replaceFirstChar { it.uppercase() }) }
                    }
                }
            },
            confirmButton = { TextButton(onClick = { showStatusDialog = false }) { Text("Cancel") } },
        )
    }
}
