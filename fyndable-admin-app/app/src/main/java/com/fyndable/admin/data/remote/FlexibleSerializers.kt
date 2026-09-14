package com.fyndable.admin.data.remote

import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerializationException
import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.serializer
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonDecoder
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonArray

/**
 * Deserializes a JSON object as a Map<String, V>. If the server sends an empty
 * JSON array `[]` instead of an empty object `{}` (common for PHP empty
 * associative arrays), it is treated as an empty map. This keeps the app from
 * crashing while the backend is being updated.
 */
class FlexibleMapSerializer<V>(private val valueSerializer: KSerializer<V>) : KSerializer<Map<String, V>> {
    private val delegate = MapSerializer(String.serializer(), valueSerializer)
    override val descriptor: SerialDescriptor = delegate.descriptor

    override fun serialize(encoder: Encoder, value: Map<String, V>) {
        delegate.serialize(encoder, value)
    }

    override fun deserialize(decoder: Decoder): Map<String, V> {
        val jsonDecoder = decoder as? JsonDecoder
            ?: throw SerializationException("Only JsonDecoder is supported")
        val element = jsonDecoder.decodeJsonElement()
        return when {
            element is JsonObject -> jsonDecoder.json.decodeFromJsonElement(delegate, element)
            element.jsonArray.isEmpty() -> emptyMap()
            else -> throw SerializationException("Expected JSON object for map, got ${element}")
        }
    }
}

object FlexibleStringMapSerializer : KSerializer<Map<String, String>> by FlexibleMapSerializer(String.serializer())
object FlexibleIntMapSerializer : KSerializer<Map<String, Int>> by FlexibleMapSerializer(Int.serializer())
object FlexibleRevenueTierMapSerializer : KSerializer<Map<String, RevenueTier>> by FlexibleMapSerializer(RevenueTier.serializer())
object FlexibleNestedStringMapSerializer : KSerializer<Map<String, Map<String, String>>> by FlexibleMapSerializer(FlexibleStringMapSerializer)

object LimitCheckFlexibleSerializer : KSerializer<LimitCheck?> {
    private val delegate = LimitCheck.serializer()
    override val descriptor: SerialDescriptor = delegate.descriptor

    override fun serialize(encoder: Encoder, value: LimitCheck?) {
        if (value != null) delegate.serialize(encoder, value)
    }

    override fun deserialize(decoder: Decoder): LimitCheck? {
        val jsonDecoder = decoder as? JsonDecoder
            ?: throw SerializationException("Only JsonDecoder is supported")
        val element = jsonDecoder.decodeJsonElement()
        return when {
            element is JsonObject -> jsonDecoder.json.decodeFromJsonElement(delegate, element)
            element.jsonArray.isEmpty() -> null
            else -> throw SerializationException("Expected JSON object for LimitCheck, got ${element}")
        }
    }
}

/**
 * Deserializes LimitChecks from a JSON object. An empty JSON array `[]` is
 * treated as `null`.
 */
object LimitChecksFlexibleSerializer : KSerializer<LimitChecks?> {
    private val delegate = LimitChecks.serializer()
    override val descriptor: SerialDescriptor = delegate.descriptor

    override fun serialize(encoder: Encoder, value: LimitChecks?) {
        if (value != null) delegate.serialize(encoder, value)
    }

    override fun deserialize(decoder: Decoder): LimitChecks? {
        val jsonDecoder = decoder as? JsonDecoder
            ?: throw SerializationException("Only JsonDecoder is supported")
        val element = jsonDecoder.decodeJsonElement()
        return when {
            element is JsonObject -> jsonDecoder.json.decodeFromJsonElement(delegate, element)
            element.jsonArray.isEmpty() -> null
            else -> throw SerializationException("Expected JSON object for LimitChecks, got ${element}")
        }
    }
}

/**
 * Deserializes a generic data class from a JSON object. If the server sends an
 * empty JSON array `[]` instead of an empty object `{}`, it uses the provided
 * default empty instance.
 */
class FlexibleObjectSerializer<T>(
    private val objectSerializer: KSerializer<T>,
    private val emptyValue: T,
) : KSerializer<T> {
    override val descriptor: SerialDescriptor = objectSerializer.descriptor

    override fun serialize(encoder: Encoder, value: T) {
        objectSerializer.serialize(encoder, value)
    }

    override fun deserialize(decoder: Decoder): T {
        val jsonDecoder = decoder as? JsonDecoder
            ?: throw SerializationException("Only JsonDecoder is supported")
        val element = jsonDecoder.decodeJsonElement()
        return when {
            element is JsonObject -> jsonDecoder.json.decodeFromJsonElement(objectSerializer, element)
            element.jsonArray.isEmpty() -> emptyValue
            else -> throw SerializationException("Expected JSON object, got ${element}")
        }
    }
}
