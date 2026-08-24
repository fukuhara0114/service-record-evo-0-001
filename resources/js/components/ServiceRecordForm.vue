<template>
    <form @submit.prevent="submit">
  <!-- 今の入力UI全部 -->

   <!-- ================= 基本情報 ================= -->
    <h3>基本情報</h3>

    <div class="grid grid-cols-4 gap-4">

      <div>
        <label class="block text-sm">受領日</label>
        <DateInputWithToday v-model="form.receivedDate" />
      </div>

      <div>
        <label class="block text-sm">状態</label>
        <select v-model="form.status">
          <option v-for="s in statuses" :key="s.processID_new" :value="s.processID_new">
            {{ s.status }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm">製品名</label>
        <input class="border p-1 w-full" v-model="form.productName">
      </div>

      <div>
        <label class="block text-sm">SN</label>
        <input class="border p-1 w-full" v-model="form.SN">
      </div>

      <div>
        <label class="block text-sm">作業内容</label>
        <select v-model="form.returnCode">
          <option v-for="r in returnCodes" :key="r.id" :value="r.id">
            {{ r.description }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm">担当者</label>
        <select v-model="form.laborID">
          <option v-for="l in labors" :key="l.id" :value="l.id">
            {{ l.laborName }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm">RMA</label>
        <input class="border p-1 w-full" v-model="form.RMA">
      </div>

      <div>
        <label class="block text-sm">WO</label>
        <input class="border p-1 w-full" v-model="form.sm_workorder">
      </div>

      <div>
        <label class="block text-sm">QUOTE</label>
        <input class="border p-1 w-full" v-model="form.quoteNum">
      </div>

      <div>
        <label class="block text-sm">CO num</label>
        <input class="border p-1 w-full" v-model="form.coNum">
      </div>

      <div>
        <label class="block text-sm">a2la</label>
        <input type="checkbox" v-model="form.a2la">
      </div>

    </div>

    <!-- ================= 販売店 ================= -->
    <h3>販売店</h3>

    <div class="grid grid-cols-2 gap-4">
      <input class="border p-1 w-full" v-model="form.dealer" placeholder="販社">
      <input class="border p-1 w-full" v-model="form.dealer_depart" placeholder="部署">
      <input class="border p-1 w-full" v-model="form.contactPerson" placeholder="担当者">
      <input class="border p-1 w-full" v-model="form.email" placeholder="メール">
      <input class="border p-1 w-full" v-model="form.phone" placeholder="Tel">
      <input class="border p-1 w-full" v-model="form.zipcode" placeholder="〒">
      <input class="border p-1 w-full" v-model="form.address1" placeholder="住所1">
      <input class="border p-1 w-full" v-model="form.address2" placeholder="住所2">
    </div>

    <!-- ================= エンドユーザー ================= -->
    <h3>エンドユーザー</h3>

    <label class="block text-sm">
      <input type="checkbox" v-model="form.deliverToEndUser">
      エンドユーザー直送
    </label>

    <div v-if="form.deliverToEndUser" class="grid grid-cols-2 gap-4">
      <input class="border p-1 w-full" v-model="form.endUser" placeholder="会社">
      <input class="border p-1 w-full" v-model="form.endUser_depart" placeholder="部署">
      <input class="border p-1 w-full" v-model="form.endUser_contactPerson" placeholder="担当者">
      <input class="border p-1 w-full" v-model="form.endUser_email" placeholder="メール">
      <input class="border p-1 w-full" v-model="form.endUser_phone" placeholder="電話">
      <input class="border p-1 w-full" v-model="form.endUser_zipcode" placeholder="〒">
      <input class="border p-1 w-full" v-model="form.endUser_address1" placeholder="住所1">
      <input class="border p-1 w-full" v-model="form.endUser_address2" placeholder="住所2">
    </div>

    <button type="submit">保存</button>
</form>
</template>

<script>
import axios from 'axios'
import DateInputWithToday from '@/components/DateInputWithToday.vue'

export default {
  components: { DateInputWithToday },
  props: ['record', 'statuses', 'returnCodes', 'labors', 'mode'],

  data() {
    return {
      form: {
        receivedDate: '',
        status: '',
        productName: '',
        SN: '',
        returnCode: '',
        laborID: '',
        RMA: '',
        sm_workorder: '',
        quoteNum: '',
        coNum: '',
        a2la: false,

        dealer: '',
        dealer_depart: '',
        contactPerson: '',
        email: '',
        phone: '',
        zipcode: '',
        address1: '',
        address2: '',

        deliverToEndUser: false,
        endUser: '',
        endUser_depart: '',
        endUser_contactPerson: '',
        endUser_email: '',
        endUser_phone: '',
        endUser_zipcode: '',
        endUser_address1: '',
        endUser_address2: ''
      }
    }
  },

  mounted() {
    if (this.record) {
      this.form = { ...this.form, ...this.record }
    }
  },

  methods: {
    async submit() {
      try {
        await axios.put(`/servicerecord/${this.form.id}`, this.form)
        alert('更新成功')
      } catch (e) {
        console.error(e)
        alert('エラー')
      }
    }
  }
}
</script>
