package com.fyndable.mobile.data.api

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.decodeFromJsonElement

object JsonUtils {
    val json = Json { ignoreUnknownKeys = true }

    inline fun <reified T> decodeFlexibleList(element: JsonElement?): List<T> {
        if (element == null) return emptyList()
        return when (element) {
            is JsonArray -> json.decodeFromJsonElement<List<T>>(element)
            is JsonObject -> {
                // Try to find the first field that is an array
                val arrayField = element.values.firstOrNull { it is JsonArray }
                if (arrayField != null) {
                    json.decodeFromJsonElement<List<T>>(arrayField)
                } else {
                    emptyList()
                }
            }
            else -> emptyList()
        }
    }
}
