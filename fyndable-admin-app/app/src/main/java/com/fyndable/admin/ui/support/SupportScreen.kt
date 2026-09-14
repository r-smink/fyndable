package com.fyndable.admin.ui.support

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
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
import com.fyndable.admin.ui.components.OpsHeader
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

    Scaffold(
        topBar = { OpsHeader(title = "Support Tickets") },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            OutlinedTextField(
                value = search,
                onValueChange = { 
                    search = it
                    viewModel.loadList(search = it.ifBlank { null })
                },
                placeholder = { Text("Search tickets...") },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null, tint = Color(0xFF94A3B8)) },
                modifier = Modifier.fillMaxWidth().padding(16.dp),
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = Color(0xFF1E293B),
                    unfocusedTextColor = Color(0xFF1E293B),
                    focusedBorderColor = Color(0xFF6366F1),
                    unfocusedBorderColor = Color(0xFFE2E8F0),
                    focusedContainerColor = Color.White,
                    unfocusedContainerColor = Color.White
                )
            )

            when (val s = listState) {
                is TicketListState.Loading -> LoadingIndicator()
                is TicketListState.Error -> ErrorView(s.message, onRetry = { viewModel.loadList() })
                is TicketListState.Success -> {
                    if (s.tickets.isEmpty()) {
                        EmptyState("No tickets found")
                    } else {
                        LazyColumn(
                            verticalArrangement = Arrangement.spacedBy(12.dp),
                            contentPadding = PaddingValues(16.dp)
                        ) {
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
        onClick = onClick,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = BorderStroke(1.dp, Color(0xFFF1F5F9))
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(
                    text = ticket.subject,
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    color = Color(0xFF1E293B),
                    modifier = Modifier.weight(1f)
                )
                StatusBadge(ticket.status)
            }
            Spacer(Modifier.height(4.dp))
            Text(
                text = ticket.tenantName,
                style = MaterialTheme.typography.bodyMedium,
                color = Color(0xFF6366F1),
                fontWeight = FontWeight.SemiBold
            )
            Spacer(Modifier.height(12.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    val priorityColor = when(ticket.priority.lowercase()) {
                        "high" -> Color(0xFFEF4444)
                        "medium" -> Color(0xFFF59E0B)
                        else -> Color(0xFF10B981)
                    }
                    Box(modifier = Modifier.size(8.dp).background(priorityColor, CircleShape))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        text = "${ticket.priority.uppercase()} PRIORITY",
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Black,
                        color = Color(0xFF94A3B8)
                    )
                }
                Text(
                    text = ticket.updatedAt.split(" ")[0], // Simplified date
                    style = MaterialTheme.typography.labelSmall,
                    color = Color(0xFF94A3B8)
                )
            }
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

    Scaffold(
        topBar = {
            TopAppBar(
                title = { 
                    Column {
                        Text("Ticket #$ticketId", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                        Text(ticket?.tenantName ?: "", fontSize = 12.sp, color = Color.White.copy(alpha = 0.7f))
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color(0xFF1E293B),
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        if (ticket == null) {
            Box(Modifier.padding(padding).fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Color(0xFF6366F1))
            }
            return@Scaffold
        }

        Column(Modifier.fillMaxSize().padding(padding)) {
            Column(
                Modifier.weight(1f).padding(horizontal = 16.dp).verticalScroll(rememberScrollState()),
            ) {
                Spacer(Modifier.height(16.dp))
                
                // Original Message
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    border = BorderStroke(1.dp, Color(0xFFE2E8F0))
                ) {
                    Column(Modifier.padding(16.dp)) {
                        Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
                            Text("Initial Request", fontWeight = FontWeight.Black, fontSize = 10.sp, color = Color(0xFF94A3B8))
                            Text(ticket.createdAt, fontSize = 10.sp, color = Color(0xFF94A3B8))
                        }
                        Spacer(Modifier.height(8.dp))
                        Text(ticket.message, style = MaterialTheme.typography.bodyMedium, color = Color(0xFF334155))
                    }
                }

                Spacer(Modifier.height(24.dp))
                SectionHeader("Conversation")
                
                ticket.replies.forEach { reply ->
                    val isStaff = reply.isStaff == 1
                    Column(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
                        horizontalAlignment = if (isStaff) Alignment.End else Alignment.Start
                    ) {
                        Surface(
                            shape = RoundedCornerShape(
                                topStart = 16.dp, 
                                topEnd = 16.dp, 
                                bottomStart = if (isStaff) 16.dp else 4.dp, 
                                bottomEnd = if (isStaff) 4.dp else 16.dp
                            ),
                            color = if (isStaff) Color(0xFF6366F1) else Color(0xFFF1F5F9),
                            contentColor = if (isStaff) Color.White else Color(0xFF334155),
                            border = if (!isStaff) BorderStroke(1.dp, Color(0xFFE2E8F0)) else null
                        ) {
                            Column(Modifier.padding(12.dp)) {
                                Text(reply.message, style = MaterialTheme.typography.bodyMedium)
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    text = reply.createdAt.split(" ")[1], // Show time only
                                    fontSize = 10.sp,
                                    color = if (isStaff) Color.White.copy(alpha = 0.7f) else Color(0xFF94A3B8),
                                    modifier = Modifier.align(Alignment.End)
                                )
                            }
                        }
                    }
                }
                Spacer(Modifier.height(16.dp))
            }

            // Reply area
            Surface(
                modifier = Modifier.fillMaxWidth(),
                color = Color.White,
                tonalElevation = 8.dp,
                shadowElevation = 8.dp
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = replyText,
                        onValueChange = { replyText = it },
                        placeholder = { Text("Type a reply...") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(24.dp),
                        maxLines = 4,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = Color(0xFF1E293B),
                            unfocusedTextColor = Color(0xFF1E293B),
                            focusedBorderColor = Color(0xFF6366F1),
                            unfocusedBorderColor = Color(0xFFE2E8F0),
                            focusedContainerColor = Color.White,
                            unfocusedContainerColor = Color.White
                        )
                    )
                    Spacer(Modifier.width(8.dp))
                    IconButton(
                        onClick = {
                            if (replyText.isNotBlank()) {
                                viewModel.reply(ticketId, replyText) { success ->
                                    if (success) replyText = ""
                                }
                            }
                        },
                        modifier = Modifier.size(48.dp).background(Color(0xFF6366F1), CircleShape)
                    ) {
                        Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Send", tint = Color.White)
                    }
                }
            }
        }
    }
}
